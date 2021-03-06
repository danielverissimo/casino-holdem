<?php

namespace Cysha\Casino\Holdem\Game;

use Cysha\Casino\Cards\Contracts\CardEvaluator;
use Cysha\Casino\Cards\Deck;
use Cysha\Casino\Game\Client;
use Cysha\Casino\Game\Contracts\Dealer as DealerContract;
use Cysha\Casino\Game\Contracts\Player;
use Cysha\Casino\Game\Contracts\Player as PlayerContract;
use Cysha\Casino\Game\PlayerCollection;
use Cysha\Casino\Game\Table as BaseTable;
use Cysha\Casino\Holdem\Cards\Evaluators\SevenCard;
use Cysha\Casino\Holdem\Exceptions\TableException;
use JsonSerializable;
use Ramsey\Uuid\Uuid;

class Table extends BaseTable implements JsonSerializable
{
    /**
     * @var Uuid
     */
    private $id;

    /**
     * @var Dealer
     */
    private $dealer;

    /**
     * @var PlayerCollection
     */
    private $players;

    /**
     * @var PlayerCollection
     */
    private $playersSatOut;

    /**
     * @var int
     */
    private $button = 0;

    /**
     * @var PlayerCollection
     */
    private $playersWaitToIn;

    /**
     * @var Round
     */
    private $currentRound;

    /**
     * @var array
     */
    private $freeSeats;


    /**
     * Table constructor.
     *
     * @param Uuid $id
     * @param DealerContract $dealer
     * @param PlayerCollection $players
     */
    private function __construct(Uuid $id, DealerContract $dealer, PlayerCollection $players)
    {
        $this->id = $id;
        $this->players = $players;
        $this->playersSatOut = PlayerCollection::make();
        $this->playersWaitToIn = PlayerCollection::make();
        $this->dealer = $dealer;

        $this->shuffleSeatedPlayers();
    }

    /**
     * @param Uuid $id
     * @param DealerContract $dealer
     * @param PlayerCollection $players
     *
     * @return Table
     */
    public static function setUp(Uuid $id, DealerContract $dealer, PlayerCollection $players)
    {
        return new self($id, $dealer, $players);
    }

    /**
     * @return Uuid
     */
    public function id(): Uuid
    {
        return $this->id;
    }

    /**
     * @return PlayerCollection
     */
    public function players(): PlayerCollection
    {
        return $this->players;
    }

    /**
     * @return PlayerCollection
     */
    public function playersWaitToIn(): PlayerCollection
    {
        return $this->playersWaitToIn;
    }

    public function removePlayersWaitToIn(Client $client)
    {
        $player = $this->playersWaitToIn()
            ->filter(function (Player $player) use ($client) {
                return $player->name() === $client->name();
            })
            ->first();

        if ($player === null) {
            throw TableException::notRegistered($client, $this);
        }

        $this->playersWaitToIn = $this->playersWaitToIn()
            ->reject(function (Player $player) use ($client) {
                return $player->name() === $client->name();
            })
            ->values();
    }

    /**
     * @return DealerContract
     */
    public function dealer(): DealerContract
    {
        return $this->dealer;
    }

    /**
     * @return int
     */
    public function button(): int
    {
        return $this->button;
    }

    /**
     * @return PlayerContract
     */
    public function locatePlayerWithButton(): ?PlayerContract
    {
        return $this->players()->get($this->button);
    }

    /**
     * @param PlayerContract $client
     */
    public function sitPlayerOut(PlayerContract $client)
    {

        $playerSitedOut = $this->playersSatOut
            ->filter(function (Player $player) use ($client) {
                return $player->id() === $client->id();
            })
            ->first();

        if ( empty($playerSitedOut) ){
            $this->playersSatOut->push($client);
        }

    }

    /**
     * @param PlayerContract $player
     */
    public function sitPlayerIn(PlayerContract $player)
    {
        $this->playersSatOut->each(function(PlayerContract $p, $index) use ($player){

            if ( $player->id() === $p->id() ){
                $this->playersSatOut->forget($index);
                return;
            }

        });
    }

    /**
     * @return PlayerCollection
     */
    public function playersSatDown(): PlayerCollection
    {
        return $this->players()->diff($this->playersSatOut)->values();
    }

    /**
     * @param PlayerContract $player
     *
     * @throws TableException
     */
    public function giveButtonToPlayer(PlayerContract $player)
    {
        $playerIndex = $this->playersSatDown()
            ->filter
            ->equals($player)
            ->keys()
            ->first();

        if ($playerIndex === null) {
            throw TableException::invalidButtonPosition();
        }

        $this->button = $playerIndex;
    }

    /**
     * Moves the button along the table seats.
     */
    public function moveButton()
    {
        ++$this->button;

        // TODO para cache game trocar players() para playersSatDown()
        if ($this->button >= $this->players()->count()) {
            $this->button = 0;
        }
    }

    public function setButton($button)
    {
        $this->button = $button;
    }

    /**
     * @param PlayerContract $findPlayer
     *
     * @return int
     */
    public function findSeat(PlayerContract $findPlayer): int
    {
        return $this->players()
            ->filter(function (PlayerContract $player) use ($findPlayer) {
                return $player->equals($findPlayer);
            })
            ->keys()
            ->first();
    }

    /**
     * @param string $playerName
     *
     * @return Player
     */
    public function findPlayerByName($playerName): PlayerContract
    {
        return $this->players()
            ->filter(function (PlayerContract $player) use ($playerName) {
                return $player->name() === $playerName;
            })
            ->first();
    }

    public function removePlayer(Client $client)
    {
        $player = $this->players()
            ->filter(function (Player $player) use ($client) {
                return $player->name() === $client->name();
            })
            ->first();

        if ($player === null) {
            throw TableException::notRegistered($client, $this);
        }

        $this->players = $this->players()
            ->reject(function (Player $player) use ($client) {
                return $player->name() === $client->name();
            })
            ->values();
    }

    public function addPlayer(Player $player)
    {
        $seat = array_random($this->freeSeats);
        $this->freeSeats = array_diff($this->freeSeats, array($seat));
        $player->setSeat($seat);

        $this->players()->push($player);

        $this->players = $this->players()->sortBy(function ($player) {
            return $player->seat();
        })->values();
    }

    public function shuffleSeatedPlayers()
    {
        $this->buildFreeSeatArray();

        foreach ($this->players() as $player){

            $seat = array_random($this->freeSeats);
            $this->freeSeats = array_diff($this->freeSeats, array($seat));
            $player->setSeat($seat);

        }

        $this->players = $this->players()->sortBy(function ($player) {
            return $player->seat();
        })->values();

    }

    public function buildFreeSeatArray()
    {

        $tableSize = 9; // $this->currentRound()->gameRules()->tableSize();

        for($i=1; $i <= $tableSize; $i++){
            $this->freeSeats[] = $i;
        }

    }


    public function dealerStartWork(Deck $deck, CardEvaluator $cardEvaluationRules){
        $this->dealer = $this->dealer()->startWork(new Deck(), new SevenCard());
    }

    /**
     * @return Round
     */
    public function currentRound(): ?Round
    {
        return $this->currentRound;
    }

    /**
     * @param Round $round
     */
    public function setCurrentRound(Round $round)
    {
        $this->currentRound = $round;
    }

    function jsonSerialize()
    {
        return [
            'id' => $this->id()->toString(),
            'button' => $this->button,
            'players' => $this->players != null ? $this->players->jsonSerialize() : null,
            'playersSatOut' => $this->playersSatOut != null ? $this->playersSatOut->jsonSerialize() : null,
            'playersSatDown' => $this->playersSatDown() != null ? $this->playersSatDown()->jsonSerialize() : null,
        ];
    }
}
