<?php

namespace Cysha\Casino\Holdem\Game;

use Carbon\Carbon;
use Cysha\Casino\Cards\Contracts\CardResults;
use Cysha\Casino\Cards\Deck;
use Cysha\Casino\Cards\HandCollection;
use Cysha\Casino\Game\Chips;
use Cysha\Casino\Game\ChipStackCollection;
use Cysha\Casino\Game\Contracts\Dealer as DealerContract;
use Cysha\Casino\Game\Contracts\GameParameters;
use Cysha\Casino\Game\Contracts\Player as PlayerContract;
use Cysha\Casino\Game\PlayerCollection;
use Cysha\Casino\Holdem\Cards\Evaluators\SevenCard;
use Cysha\Casino\Holdem\Cards\Results\SevenCardResult;
use Cysha\Casino\Holdem\Cards\SevenCardResultCollection;
use Cysha\Casino\Holdem\Exceptions\RoundException;
use Illuminate\Support\Facades\Log;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

class Round implements JsonSerializable
{
    /**
     * @var Uuid
     */
    private $id;

    /**
     * @var Table
     */
    private $table;

    /**
     * @var ChipStackCollection
     */
    private $betStacks;

    /**
     * @var PlayerCollection
     */
    private $foldedPlayers;

    /**
     * @var ChipPotCollection
     */
    private $chipPots;

    /**
     * @var ChipPot
     */
    private $currentPot;

    /**
     * @var ActionCollection
     */
    private $actions;

    /**
     * @var PlayerCollection
     */
    private $leftToAct;

    /**
     * @var GameParameters
     */
    private $gameRules;

    /**
     * @var PlayerCollection
     */
    private $winningPlayers;

    /**
     * @var HandCollection
     */
    private $showDownHands;

    /**
     * @var SevenCardResultCollection
     */
    private $evaluations;

    /**
     * @var Carbon
     */
    private $lastActionStartedAt;

    /**
     * @var bool
     */
    private $actionInProgress = false;

    /**
     * Round constructor.
     *
     * @param Uuid $id
     * @param Table $table
     * @param GameParameters $gameRules
     */
    private function __construct(Uuid $id, Table $table, GameParameters $gameRules)
    {
        $this->id = $id;
        $this->table = $table;
        $this->chipPots = ChipPotCollection::make();
        $this->currentPot = ChipPot::create();
        $this->betStacks = ChipStackCollection::make();
        $this->foldedPlayers = PlayerCollection::make();
        $this->actions = ActionCollection::make();
        $this->leftToAct = LeftToAct::make();
        $this->gameRules = $gameRules;
        $this->winningPlayers = PlayerCollection::make();
        $this->showDownHands = HandCollection::make();
        $this->evaluations = SevenCardResultCollection::make();

        // shuffle the deck ready
        $this->table()->dealerStartWork(new Deck(), new SevenCard());
        $this->dealer()->shuffleDeck();

        // add the default pot to the chipPots
        $this->chipPots->push($this->currentPot);

        // init the betStacks and actions for each player
        $this->resetBetStacks();

        $this->setupLeftToAct();
    }

    /**
     * Start a Round of poker.
     *
     * @param Uuid $id
     * @param Table $table
     * @param GameParameters $gameRules
     *
     * @return Round
     */
    public static function start(Uuid $id, Table $table, GameParameters $gameRules): Round
    {
        return new static($id, $table, $gameRules);
    }

    /**
     * Run the cleanup procedure for an end of Round.
     */
    public function end()
    {
        $this->showDownHands();

        $this->dealer()->checkCommunityCards();

        $this->collectChipTotal();

        $this->distributeWinnings();

        $this->table()->moveButton();
    }

    /**
     * @return Uuid
     */
    public function id(): Uuid
    {
        return $this->id;
    }

    public function setId($id)
    {
        return $this->id = $id;
    }

    /**
     * @return DealerContract
     */
    public function dealer(): DealerContract
    {
        return $this->table->dealer();
    }

    /**
     * @return PlayerCollection
     */
    public function players(): PlayerCollection
    {
        return $this->table->players();
    }

    /**
     * @return PlayerCollection
     */
    public function playersStillIn(): PlayerCollection
    {
        return $this->table->playersSatDown()->diff($this->foldedPlayers())->values();
    }

    /**
     * @return boolean
     */
    public function isPlayersRemainAllInn(): bool
    {

        $allInCount = 0;
        $playersStillInCount = $this->playersStillIn()->count();

        $this->actions()->reverse()->each(function (Action $action) use(&$allInCount){

            if ( $action->action() === Action::ALLIN ){
                $allInCount++;
            }

        });

        if ( $playersStillInCount > 0 && $allInCount === $playersStillInCount ){
            return true;
        }

        return false;
    }

    /**
     * @return boolean
     */
    public function lessThenOnePlayerWithChip(): bool
    {

        $playerQtdy = $this->playersStillIn()->count();
        $this->playersStillIn()->each(function(Player $player) use(&$playerQtdy){

           if ( $player->chipStack()->amount() === 0 ){
               $playerQtdy--;
           }

        });

        if ( $playerQtdy <= 1){
            return true;
        }

        return false;
    }

    /**
     * @return PlayerCollection
     */
    public function foldedPlayers(): PlayerCollection
    {
        return $this->foldedPlayers;
    }

    /**
     * @return PlayerCollection
     */
    public function winningPlayers(): PlayerCollection
    {
        return $this->winningPlayers;
    }

    public function resetWinningPlayers()
    {
         $this->winningPlayers = PlayerCollection::make();
    }

    /**
     * @return HandCollection
     */
    public function showDownHands()
    {
        if ( $this->playersStillIn()->count() > 1 ) {
            $this->showDownHands = $this->playersStillIn()
                ->map(function (Player $player) {
                    return $this->table()->dealer()->playerHand($player);
                });
        }
    }

    /**
     * @return ActionCollection
     */
    public function actions(): ActionCollection
    {
        return $this->actions;
    }

    public function playerActionAfterIndex($playerId, $lastIndex){

        return $this->actions()->each(function(Action $action, $index) use($playerId, $lastIndex){
            $player = $action->player();
            if ( $player instanceof Player && ((int)$playerId) === $player->id() && $index > $lastIndex ){
                return true;
            }
        })->keys()->first();

    }

    /**
     * @return LeftToAct
     */
    public function leftToAct(): LeftToAct
    {
        return $this->leftToAct;
    }

    /**
     * @return Table
     */
    public function table(): Table
    {
        return $this->table;
    }

    /**
     * @return ChipStackCollection
     */
    public function betStacks(): ChipStackCollection
    {
        return $this->betStacks;
    }

    /**
     * @return GameParameters
     */
    public function gameRules(): GameParameters
    {
        return $this->gameRules;
    }

    /**
     * @return int
     */
    public function betStacksTotal(): int
    {
        return $this->betStacks()->total()->amount();
    }

    public function dealHands()
    {
        $players = $this->table()
            ->playersSatDown()
            ->resetPlayerListFromSeat($this->table()->button() + 1);

        $this->dealer()->dealHands($players);
    }

    /**
     * Runs over each chipPot and assigns the chips to the winning player.
     */
    private function distributeWinnings()
    {

        Log::info('distributeWinnings - round_id: ' . $this->id);
        Log::info('communityCards: ' . $this->dealer()->communityCards());

        $this->chipPots()
            ->reverse()
            ->each(function (ChipPot $chipPot) {

                // if only 1 player participated to pot, he wins it no arguments
                if ($chipPot->players()->count() === 1) {
                    $potTotal = $chipPot->chips()->total();

                    $player = $chipPot->players()->first();
                    $player->chipStack()->add($potTotal);
                    $player->stackWin()->add($potTotal);

                    $this->chipPots()->remove($chipPot);

                    if ($this->winningPlayers()->findByName($player->name()) === null) {
                        $this->winningPlayers->push($player);
                    }

                    return;
                }

                $activePlayers = $chipPot->players()->diff($this->foldedPlayers());

                $playerHands = $this->dealer()->hands()->findByPlayers($activePlayers);
                $evaluate = $this->dealer()->evaluateHands($this->dealer()->communityCards(), $playerHands);

                // Add evaluation message for all hands
                $playerHands->each(function (HandCollection $results) {

                    $results->each(function (SevenCardResult $hand) {
                        $this->evaluations->push($hand);
                    });

                });

                // if just 1, the player with that hand wins
                if ($evaluate->count() === 1) {

                    $player = $evaluate->first()->hand()->player();
                    $potTotal = $chipPot->chips()->total();

                    $player->chipStack()->add($potTotal);
                    $player->stackWin()->add($potTotal);

                    $this->chipPots()->remove($chipPot);

                    if ($this->winningPlayers()->findByName($player->name()) === null) {
                        $this->winningPlayers->push($player);
                    }

                    Log::info('distributeWinnings (1): ' . $evaluate);

                } else {

                    // if > 1 hand is evaluated as highest, split the pot evenly between the players
                    $potTotal = $chipPot->chips()->total();

                    // split the pot between the number of players
                    $splitTotal = Chips::fromAmount(($potTotal->amount() / $evaluate->count()));
                    $evaluate->each(function (CardResults $result) use ($splitTotal) {

                        $player = $result->hand()->player();
                        $player->chipStack()->add($splitTotal);
                        $player->stackWin()->add($splitTotal);

                        if ($this->winningPlayers()->findByName($player->name()) === null) {
                            $this->winningPlayers->push($player);
                        }

                    });

                    $this->chipPots()->remove($chipPot);
                }
            });

    }

    /**
     * @param Player $actualPlayer
     *
     * @return bool
     */
    public function playerIsStillIn(PlayerContract $actualPlayer)
    {
        $playerCount = $this->playersStillIn()->filter->equals($actualPlayer)->count();

        return $playerCount === 1;
    }

    /**
     * @return PlayerContract
     */
    public function playerWithButton(): ?PlayerContract
    {
        // TODO para cache game trocar players() para playersSatDown()

        return $this->table()->locatePlayerWithButton();
    }

    public function getPlayer($playerId): ?PlayerContract
    {
        return $this->players()
            ->filter(function (PlayerContract $player) use ($playerId) {
                return $player->id() === $playerId;
            })
            ->first();
    }

    /**
     * @return PlayerContract
     */
    public function playerWithSmallBlind(): ?PlayerContract
    {
        // TODO para cache game trocar players() para playersSatDown()

        $button = $this->table()->button();
        if ($this->table()->players()->count() > 2) {
            $button = $this->table()->button() + 1;

            if ( $button >= $this->table()->players()->count() ){
                $button = 0;
            }
        }

        $playersSatDown = $this->table()->players();
        return $playersSatDown->count() > 1 ? $playersSatDown->get($button) : null;
    }

    /**
     * @return PlayerContract
     */
    public function playerWithBigBlind(): ?PlayerContract
    {
        // TODO para cache game trocar players() para playersSatDown()

        $button = $this->table()->button();
        if ($this->table()->players()->count() > 2) {
            $button = $this->table()->button() + 2;

            if ( $button == $this->table()->players()->count() ){
                $button = 0;
            }else if ( $button > $this->table()->players()->count() ){
                $button = 1;
            }
        }else{

            $button++;
            if ( $button >= $this->table()->players()->count() ){
                $button = 0;
            }

        }

        $playersSatDown = $this->table()->players();
        return $playersSatDown->count() > 1 ? $playersSatDown->get($button) : null;
    }

    /**
     * @param PlayerContract $player
     */
    public function postSmallBlind(PlayerContract $player)
    {
        // Take chips from player
        $chips = $this->smallBlind();

        $this->postBlind($player, $chips);

        $this->actions()->push(new Action($player, Action::SMALL_BLIND, ['chips' => $this->smallBlind()]));
        $this->leftToAct = $this->leftToAct()->playerHasActioned($player, LeftToAct::SMALL_BLIND);
    }

    /**
     * @param PlayerContract $player
     */
    public function postBigBlind(PlayerContract $player)
    {
        // Take chips from player
        $chips = $this->bigBlind();

        $this->postBlind($player, $chips);

        $this->actions()->push(new Action($player, Action::BIG_BLIND, ['chips' => $this->bigBlind()]));
        $this->leftToAct = $this->leftToAct()->playerHasActioned($player, LeftToAct::BIG_BLIND);
    }

    /**
     * @param PlayerCollection $players
     * @param int $ante
     */
    public function postAnte(PlayerCollection $players, int $ante)
    {

        Log::info('postAnte...');

        foreach ($players as $player){

            // Take chips from player
            $playerChips = $player->chipStack();

            if ( $playerChips->amount() < $ante ){
                $this->playerPushesAllIn($player);
            }else{

                $chips = Chips::fromAmount($ante);
                $this->postBlind($player, $chips);
                $this->actions()->push(new Action($player, Action::ANTE, ['chips' => $chips]));

            }

        }

        $this->collectChipTotal();
    }

    /**
     * @return Chips
     */
    private function smallBlind(): Chips
    {
        return Chips::fromAmount($this->gameRules()->smallBlind()->amount());
    }

    /**
     * @return Chips
     */
    private function bigBlind(): Chips
    {
        return Chips::fromAmount($this->gameRules()->bigBlind()->amount());
    }

    /**
     * @return ChipPot
     */
    public function currentPot(): ChipPot
    {
        return $this->currentPot;
    }

    /**
     * @return ChipPotCollection
     */
    public function chipPots(): ChipPotCollection
    {
        return $this->chipPots;
    }

    /**
     * @param PlayerContract $player
     *
     * @return Chips
     */
    public function playerBetStack(PlayerContract $player): Chips
    {
        return $this->betStacks->findByPlayer($player);
    }

    /**
     * @param PlayerContract $player
     * @param Chips $chips
     */
    private function postBlind(PlayerContract $player, $chips)
    {
        $player->chipStack()->subtract($chips);

        // Add chips to player's table stack
        $this->betStacks->put($player->name(), $chips);
    }

    /**
     * @return PlayerContract|false
     */
    public function whosTurnIsIt()
    {
        $nextPlayer = $this->leftToAct()->getNextPlayer();
        if ($nextPlayer === null) {
            return false;
        }

        return $this->players()
            ->filter(function (PlayerContract $player) use ($nextPlayer) {
                return $player->name() === $nextPlayer['player'];
            })
            ->first();
    }

    /**
     * @return ChipPotCollection
     */
    public function collectChipTotal(): ChipPotCollection
    {
        $allInActionsThisRound = $this->leftToAct()->filter(function (array $value) {
            return $value['action'] === LeftToAct::ALL_IN;
        });

        $orderedBetStacks = $this->betStacks()
            ->reject(function (Chips $chips, $playerName) {
                $foldedPlayer = $this->foldedPlayers()->findByName($playerName);
                if ($foldedPlayer) {
                    return true;
                }

                return false;
            })
            ->sortByChipAmount();

        if ($allInActionsThisRound->count() > 1 && $orderedBetStacks->unique()->count() > 1) {
            $orderedBetStacks->each(function (Chips $playerChips, $playerName) use ($orderedBetStacks) {
                $remainingStacks = $orderedBetStacks->filter(function (Chips $chips) {
                    return $chips->amount() !== 0;
                });

                $this->currentPot = ChipPot::create();
                $this->chipPots()->push($this->currentPot);

                $player = $this->players()->findByName($playerName);
                $allInAmount = Chips::fromAmount($orderedBetStacks->findByPlayer($player)->amount());

                $remainingStacks->each(function (Chips $chips, $playerName) use ($allInAmount, $orderedBetStacks) {
                    $player = $this->players()->findByName($playerName);

                    $stackChips = Chips::fromAmount($allInAmount->amount());

                    if (($chips->amount() - $stackChips->amount()) <= 0) {
                        $stackChips = Chips::fromAmount($chips->amount());
                    }

                    $chips->subtract($stackChips);
                    $this->currentPot->addChips($stackChips, $player);
                    $orderedBetStacks->put($playerName, Chips::fromAmount($chips->amount()));
                });
            });

            // sort the pots so we get rid of any empty ones
            $this->chipPots = $this->chipPots
                ->filter(function (ChipPot $chipPot) {
                    return $chipPot->total()->amount() !== 0;
                })
                ->values();

            // grab anyone that folded
            $this->betStacks()
                ->filter(function (Chips $chips, $playerName) {
                    $foldedPlayer = $this->foldedPlayers()->findByName($playerName);
                    if ($foldedPlayer && $chips->amount() > 0) {
                        return true;
                    }

                    return false;
                })
                ->each(function (Chips $chips, $playerName) use ($orderedBetStacks) {
                    $player = $this->players()->findByName($playerName);

                    $stackChips = Chips::fromAmount($chips->amount());

                    $chips->subtract($stackChips);
                    $this->chipPots->get(0)->addChips($stackChips, $player);
                    $orderedBetStacks->put($playerName, Chips::fromAmount($chips->amount()));
                });
        } else {
            $this->betStacks()->each(function (Chips $chips, $playerName) {
                $this->currentPot()->addChips($chips, $this->players()->findByName($playerName));
            });
        }

        $this->resetBetStacks();

        return $this->chipPots();
    }

    /**
     * Deal the Flop.
     */
    public function dealFlop()
    {
        if ($this->dealer()->communityCards()->count() !== 0) {
            throw RoundException::flopHasBeenDealt();
        }
        if ($player = $this->whosTurnIsIt()) {
            throw RoundException::playerStillNeedsToAct($player);
        }

        $this->collectChipTotal();

        $seat = $this->table()->findSeat($this->playerWithSmallBlind());

        if ($this->table()->playersSatDown()->count() == 2) {
            $seat++;
        }

        $this->resetPlayerList($seat);

        $this->dealer()->dealCommunityCards(3);
        $this->actions()->push(new Action($this->dealer(), Action::DEALT_FLOP, [
            'communityCards' => $this->dealer()->communityCards()->only(range(0, 2)),
        ]));
    }

    /**
     * Deal the turn card.
     */
    public function dealTurn()
    {
        if ($this->dealer()->communityCards()->count() !== 3) {
            throw RoundException::turnHasBeenDealt();
        }
        if (($player = $this->whosTurnIsIt()) !== false) {
            throw RoundException::playerStillNeedsToAct($player);
        }
//        if ($this->playersStillIn()->count() == 0){
//            throw RoundException::hasNoPlayerIn();
//        }

        $this->collectChipTotal();

        $seat = $this->table()->findSeat($this->playerWithSmallBlind());

        if ($this->table()->playersSatDown()->count() == 2) {
            $seat++;
        }

        $this->resetPlayerList($seat);

        $this->dealer()->dealCommunityCards(1);
        $this->actions()->push(new Action($this->dealer(), Action::DEALT_TURN, [
            'communityCards' => $this->dealer()->communityCards()->only(3),
        ]));
    }

    /**
     * Deal the river card.
     */
    public function dealRiver()
    {
        if ($this->dealer()->communityCards()->count() !== 4) {
            throw RoundException::riverHasBeenDealt();
        }
        if (($player = $this->whosTurnIsIt()) !== false) {
            throw RoundException::playerStillNeedsToAct($player);
        }

        $this->collectChipTotal();

        $seat = $this->table()->findSeat($this->playerWithSmallBlind());

        if ($this->table()->playersSatDown()->count() == 2) {
            $seat++;
        }

        $this->resetPlayerList($seat);

        $this->dealer()->dealCommunityCards(1);
        $this->actions()->push(new Action($this->dealer(), Action::DEALT_RIVER, [
            'communityCards' => $this->dealer()->communityCards()->only(4),
        ]));
    }

    /**
     * @throws RoundException
     */
    public function checkPlayerTryingToAct(PlayerContract $player)
    {
        $actualPlayer = $this->whosTurnIsIt();
        if ($actualPlayer === false) {
            throw RoundException::noPlayerActionsNeeded();
        }
        if ($player !== $actualPlayer) {
            throw RoundException::playerTryingToActOutOfTurn($player, $actualPlayer);
        }
    }

    /**
     * @param PlayerContract $player
     *
     * @throws RoundException
     */
    public function playerCalls(PlayerContract $player)
    {
        $this->checkPlayerTryingToAct($player);

        $highestChipBet = $this->highestBet();

        // current highest bet - currentPlayersChipStack
        $amountLeftToBet = Chips::fromAmount($highestChipBet->amount() - $this->playerBetStack($player)->amount());

        $chipStackLeft = Chips::fromAmount($player->chipStack()->amount() - $amountLeftToBet->amount());

        if ($chipStackLeft->amount() <= 0) {
            $amountLeftToBet = Chips::fromAmount($player->chipStack()->amount());
            $chipStackLeft = Chips::zero();
        }

        $action = $chipStackLeft->amount() === 0 ? Action::ALLIN : Action::CALL;
        $this->actions->push(new Action($player, $action, ['chips' => $amountLeftToBet]));

        $this->placeChipBet($player, $amountLeftToBet);

        $action = $chipStackLeft->amount() === 0 ? LeftToAct::ALL_IN : LeftToAct::ACTIONED;
        $this->leftToAct = $this->leftToAct()->playerHasActioned($player, $action);
    }

    /**
     * @param PlayerContract $player
     * @param Chips $chips
     *
     * @throws RoundException
     */
    public function playerRaises(PlayerContract $player, Chips $chips)
    {
        $this->checkPlayerTryingToAct($player);

        $highestChipBet = $this->highestBet();
        if ($chips->amount() < $highestChipBet->amount()) {
            throw RoundException::raiseNotHighEnough($chips, $highestChipBet);
        }

        $chipStackLeft = Chips::fromAmount($player->chipStack()->amount() - $chips->amount());

        $action = $chipStackLeft->amount() === 0 ? Action::ALLIN : Action::RAISE;
        $this->actions->push(new Action($player, $action, ['chips' => $chips]));

        $this->placeChipBet($player, $chips);

        $action = $chipStackLeft->amount() === 0 ? LeftToAct::ALL_IN : LeftToAct::AGGRESSIVELY_ACTIONED;
        $this->leftToAct = $this->leftToAct()->playerHasActioned($player, $action);
    }

    /**
     * @param PlayerContract $player
     *
     * @throws RoundException
     */
    public function playerFoldsHand(PlayerContract $player)
    {
        $this->checkPlayerTryingToAct($player);

        $this->actions()->push(new Action($player, Action::FOLD));

        $this->foldedPlayers->push($player);
        $this->leftToAct = $this->leftToAct()->removePlayer($player);
    }

    /**
     * @param PlayerContract $player
     *
     * @throws RoundException
     */
    public function playerPushesAllIn(PlayerContract $player)
    {
        $this->checkPlayerTryingToAct($player);

        // got the players chipStack
        $chips = $player->chipStack();

        // gotta create a new chip obj here cause of PHPs /awesome/ objRef ability :D
        $this->actions()->push(new Action($player, Action::ALLIN, ['chips' => Chips::fromAmount($chips->amount())]));

        $this->placeChipBet($player, $chips);
        $this->leftToAct = $this->leftToAct()->playerHasActioned($player, LeftToAct::ALL_IN);
    }

    /**
     * @param PlayerContract $player
     *
     * @throws RoundException
     */
    public function playerChecks(PlayerContract $player)
    {
        $this->checkPlayerTryingToAct($player);

        if ($this->playerBetStack($player)->amount() !== $this->betStacks()->max()->amount()) {
            throw RoundException::cantCheckWithBetActive();
        }

        $this->actions()->push(new Action($player, Action::CHECK));
        $this->leftToAct = $this->leftToAct()->playerHasActioned($player, LeftToAct::ACTIONED);
    }

    /**
     * @return Chips
     */
    private function highestBet(): Chips
    {
        return Chips::fromAmount($this->betStacks()->max(function (Chips $chips) {
                return $chips->amount();
            }) ?? 0);
    }

    /**
     * @param PlayerContract $player
     * @param Chips $chips
     */
    private function placeChipBet(PlayerContract $player, Chips $chips)
    {
        if ($player->chipStack()->amount() < $chips->amount()) {
            throw RoundException::notEnoughChipsInChipStack($player, $chips);
        }

        // add the chips to the players tableStack first
        $this->playerBetStack($player)->add($chips);

        // then remove it off their actual stack
        $player->bet($chips);
    }

    /**
     * Reset the chip stack for all players.
     */
    private function resetBetStacks()
    {
        $this->players()->each(function (PlayerContract $player) {
            $this->betStacks->put($player->name(), Chips::zero());
        });
    }

    /**
     * Reset the leftToAct collection.
     */
    public function setupLeftToAct()
    {

        if ($this->table()->players()->count() === 2) {

            if ( $this->dealer()->communityCards()->count() > 0 ){

                $button = $this->table()->button() === 1 ? 0 : 1;

                $this->leftToAct = $this->leftToAct
                    ->setup($this->players())
                    ->resetPlayerListFromSeat($button);

            }else{

                $this->leftToAct = $this->leftToAct()->setup($this->players())
                    ->resetPlayerListFromSeat($this->table()->button());

            }

            return;

        }

        $this->leftToAct = $this->leftToAct
            ->setup($this->players())
            ->resetPlayerListFromSeat($this->table()->button() + 1);
    }

//    public function noPlayersNeedsToAct(){
//
//        $leftToAct = $this->leftToAct();
//
//
//        foreach ($leftToAct as $l) {
//
//            if ( $l['action'] === LeftToAct::AGGRESSIVELY_ACTIONED ||  ){
//                $canCheck = false;
//                break;
//            }
//        }
//
//    }

    /**
     * @param PlayerContract $player
     */
    public function sitPlayerOut(PlayerContract $player)
    {
        $this->table()->sitPlayerOut($player);
        $this->leftToAct = $this->leftToAct()->removePlayer($player);
    }

    /**
     * @var int
     */
    public function resetPlayerList(int $seat)
    {
        $this->leftToAct = $this->leftToAct
            ->resetActions()
            ->sortBySeats()
            ->resetPlayerListFromSeat($seat);
    }

    public function lastActionStartedAt(){
        return $this->lastActionStartedAt;
    }

    public function setLastActionStartedAt(Carbon $lastActionStartedAt){
        $this->lastActionStartedAt = $lastActionStartedAt;
    }

    public function actionInProgress(){
        return $this->actionInProgress;
    }

    public function startActionProgress(){
        $this->actionInProgress = true;
    }

    public function stopActionProgress(){
        $this->actionInProgress = false;
    }

    function jsonSerialize()
    {

        $playerWithButton = $this->playerWithButton();
        $playerWithSmallBlind = $this->playerWithSmallBlind();
        $playerWithBigBlind = $this->playerWithBigBlind();
        $communityCards = $this->dealer()->communityCards();
        $playersStillIn = $this->playersStillIn();

        return [
            'id' => $this->id,
            'table' => $this->table != null ? $this->table->jsonSerialize() : null,
            'betStacks' => $this->table != null ? $this->table->jsonSerialize() : null,
            'foldedPlayers' => $this->foldedPlayers != null ? $this->foldedPlayers->jsonSerialize() : null,
            'playersStillIn' => $playersStillIn != null ? $playersStillIn : null,
            'winningPlayers' => $this->winningPlayers != null ? $this->winningPlayers->jsonSerialize() : null,
            'chipPots' => $this->chipPots != null ? $this->chipPots->jsonSerialize() : null,
            'currentPot' => $this->currentPot != null ? $this->currentPot->jsonSerialize() : null,
            'actions' => $this->actions != null ? $this->actions->jsonSerialize() : null,
            'leftToAct' => $this->leftToAct != null ? $this->leftToAct->jsonSerialize() : null,
            'gameRules' => $this->gameRules != null ? $this->gameRules->jsonSerialize() : null,
            'playerWithButton' => $playerWithButton != null ? $playerWithButton->jsonSerialize() : null,
            'playerWithSmallBlind' => $playerWithSmallBlind != null ? $playerWithSmallBlind->jsonSerialize() : null,
            'playerWithBigBlind' => $playerWithBigBlind != null ? $playerWithBigBlind->jsonSerialize() : null,
            'communityCards' => $communityCards != null ? $communityCards->jsonSerialize() : null,
            'showDownHands' => $this->showDownHands != null ? $this->showDownHands->jsonSerialize() : null,
            'evaluations' => $this->evaluations != null ? $this->evaluations->jsonSerialize() : null,
        ];
    }
}