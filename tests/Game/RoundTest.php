<?php

namespace Cysha\Casino\Holdem\Tests\Game;

use Cysha\Casino\Game\Chips;
use Cysha\Casino\Game\Client;
use Cysha\Casino\Holdem\Game\Action;
use Cysha\Casino\Holdem\Game\ActionCollection;
use Cysha\Casino\Holdem\Game\CashGame;
use Cysha\Casino\Holdem\Game\Parameters\CashGameParameters;
use Cysha\Casino\Holdem\Game\Player;
use Cysha\Casino\Holdem\Game\Round;
use Cysha\Casino\Holdem\Game\Table;
use Ramsey\Uuid\Uuid;

class RoundTest extends BaseGameTestCase
{
    /** @test */
    public function it_can_start_a_round_on_a_table()
    {
        $id = Uuid::uuid4();
        $game = $this->createGenericGame();
        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start($id, $game->tables()->first(), $gameRules);

        $this->assertInstanceOf(Uuid::class, $round->id());
        $this->assertEquals($id->toString(), $round->id()->toString());
        $this->assertCount(4, $round->players());
    }

    /** @test */
    public function the_button_starts_with_the_first_player()
    {
        $game = $this->createGenericGame();

        $table = $game->tables()->first();
        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
    }

    /** @test */
    public function the_second_player_is_the_small_blind()
    {
        $game = $this->createGenericGame();

        $table = $game->tables()->first();
        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
        $player2 = $table->players()->get(1);
        $this->assertEquals($round->playerWithSmallBlind(), $player2);
    }

    /** @test */
    public function the_third_player_is_the_big_blind()
    {
        $game = $this->createGenericGame();

        $table = $game->tables()->first();
        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
        $player3 = $table->players()->get(2);
        $this->assertEquals($round->playerWithBigBlind(), $player3);
    }

    /** @test */
    public function the_small_blind_is_moved_when_the_second_player_sit_out()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();

        $table->sitPlayerOut($table->playersSatDown()->get(1));
        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
        $player3 = $table->playersSatDown()->get(1);
        $this->assertEquals($round->playerWithSmallBlind(), $player3);
    }

    /** @test */
    public function the_big_blind_is_moved_when_the_third_player_sit_out()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();

        $table->sitPlayerOut($table->playersSatDown()->get(2));
        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $this->assertEquals($round->playerWithButton(), $table->players()->first());
        $player3 = $table->playersSatDown()->get(2);
        $this->assertEquals($round->playerWithBigBlind(), $player3);
    }

    /** @test */
    public function the_small_blind_is_moved_to_the_fourth_player_if_player_2_and_3_sit_out()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();

        $table->sitPlayerOut($table->players()->get(1)); // player 2
        $table->sitPlayerOut($table->players()->get(2)); // player 3
        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $player = $table->playersSatDown()->get(0);
        $this->assertEquals($round->playerWithSmallBlind(), $player);
    }

    /** @test */
    public function if_there_are_only_2_players_then_the_player_with_button_is_small_blind()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();

        $table->sitPlayerOut($table->players()->get(2)); // player 3
        $table->sitPlayerOut($table->players()->get(3)); // player 4
        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $player1 = $table->playersSatDown()->get(0);
        $this->assertEquals($round->playerWithButton(), $player1, 'Button is with the wrong player');
        $this->assertEquals($round->playerWithSmallBlind(), $player1, 'small blind is with the wrong player');

        $player2 = $table->playersSatDown()->get(1);
        $this->assertEquals($round->playerWithBigBlind(), $player2, 'big blind is with the wrong player');
    }

    /** @test */
    public function button_will_start_on_first_sat_down_player()
    {
        $xLink = Client::register(1, 'xLink', Chips::fromAmount(5500));
        $jesus = Client::register(2, 'jesus', Chips::fromAmount(5500));
        $melk = Client::register(3, 'melk', Chips::fromAmount(5500));
        $bob = Client::register(4, 'bob', Chips::fromAmount(5500));
        $blackburn = Client::register(5, 'blackburn', Chips::fromAmount(5500));

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        // we got a game
        $game = CashGame::setUp(Uuid::uuid4(), 'Demo Cash Game', $gameRules);

        // register clients to game
        $game->registerPlayer($xLink, Chips::fromAmount(5000)); // x
        $game->registerPlayer($jesus, Chips::fromAmount(5000)); //
        $game->registerPlayer($melk, Chips::fromAmount(5000)); // x
        $game->registerPlayer($bob, Chips::fromAmount(5000)); //
        $game->registerPlayer($blackburn, Chips::fromAmount(5000)); //

        $game->assignPlayersToTables(); // table has max of 9 or 5 players in holdem

        /** @var Table $table */
        $table = $game->tables()->first();
        $table->sitPlayerOut($table->players()->get(0)); // player 1
        $table->sitPlayerOut($table->players()->get(2)); // player 3

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $player2 = $table->players()->get(1);
        $this->assertEquals($round->playerWithButton(), $player2, 'Button is with the wrong player');
        $player4 = $table->players()->get(3);
        $this->assertEquals($round->playerWithSmallBlind(), $player4, 'small blind is with the wrong player');

        $player5 = $table->players()->get(4);
        $this->assertEquals($round->playerWithBigBlind(), $player5, 'big blind is with the wrong player');
    }

    /** @test */
    public function small_blind_from_player_gets_posted_and_added_to_the_pot()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();
        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);
        /*
        [
        xLink: 0, // button
        jesus: 25, // SB
        melk: 50, // BB
        bob: 0,
        ]
         */

        $round->postSmallBlind($player2);
        $this->assertEquals(Chips::fromAmount(25), $round->playerBetStack($player2));

        $round->postBigBlind($player3);
        $this->assertEquals(Chips::fromAmount(50), $round->playerBetStack($player3));
    }

    /** @test */
    public function on_round_start_deal_hands()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();
        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $this->assertCount(2, $round->dealer()->playerHand($player1));
        $this->assertCount(2, $round->dealer()->playerHand($player2));
        $this->assertCount(2, $round->dealer()->playerHand($player3));
        $this->assertCount(2, $round->dealer()->playerHand($player4));
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function on_round_start_stood_up_players_dont_get_dealt_a_hand()
    {
        $game = $this->createGenericGame();

        /** @var Table $table */
        $table = $game->tables()->first();
        $player4 = $table->playersSatDown()->get(3);

        $table->sitPlayerOut($player4);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        // This should throw an exception
        $round->dealer()->playerHand($player4);
    }

    /** @test */
    public function fifth_player_in_proceedings_is_prompted_to_action_after_round_start_when_player_4_is_stood_up()
    {
        $game = $this->createGenericGame(5);

        /** @var Table $table */
        $table = $game->tables()->first();
        $player1 = $table->playersSatDown()->first(); // Button
        $player2 = $table->playersSatDown()->get(1); // SB
        $player3 = $table->playersSatDown()->get(2); // BB
        $player4 = $table->playersSatDown()->get(3); // x [Sat out]
        $player5 = $table->playersSatDown()->get(4); // [turn]

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->sitPlayerOut($player4);

        $round->postSmallBlind($player2);
        $round->postBigBlind($player3);

        $this->assertEquals($player5, $round->whosTurnIsIt());
    }

    /** @test */
    public function fourth_player_calls_the_hand_after_blinds_are_posted()
    {
        $game = $this->createGenericGame(5);

        /** @var Table $table */
        $table = $game->tables()->first();
        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first(); // Button
        $player2 = $table->playersSatDown()->get(1); // SB
        $player4 = $table->playersSatDown()->get(3); // x [Sat out]

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($player1);
        $round->postBigBlind($player2);

        $round->playerCalls($player4);

        $this->assertEquals(50, $round->playerBetStack($player4)->amount());
        $this->assertEquals(950, $player4->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(3)->chipStack()->amount());
        $this->assertEquals(125, $round->betStacks()->total()->amount());
    }

    /** @test */
    public function player_pushes_all_in()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();
        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($player2); // 25
        $round->postBigBlind($player3); // 50

        $round->playerCalls($player4); // 50
        $round->playerPushesAllIn($player1); // 1000

        $this->assertEquals(1000, $round->playerBetStack($player1)->amount());
        $this->assertEquals(0, $player1->chipStack()->amount());
        $this->assertEquals(0, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(1125, $round->betStacks()->total()->amount());
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function player_cant_check_when_bet_has_been_made()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();
        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($player2); // 25
        $round->postBigBlind($player3); // 50

        $round->playerRaises($player4, Chips::fromAmount(250)); // 250
        $round->playerChecks($player1); // 0
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function fifth_player_tries_to_raise_the_hand_after_blinds_without_enough_chips()
    {
        $game = $this->createGenericGame(5);

        /** @var Table $table */
        $table = $game->tables()->first();

        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player4 = $table->playersSatDown()->get(3);
        $player5 = $table->playersSatDown()->get(4);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($player1);
        $round->postBigBlind($player2);

        $round->playerCalls($player4);
        $round->playerRaises($player5, Chips::fromAmount(100000));
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function random_player_tries_to_fold_their_hand_after_blinds()
    {
        $game = $this->createGenericGame(5);

        /** @var Table $table */
        $table = $game->tables()->first();

        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $randomPlayer = Player::fromClient(Client::register(Uuid::uuid4(), 'Random Player', Chips::fromAmount(1)));

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($player1);
        $round->postBigBlind($player2);
        $round->playerFoldsHand($randomPlayer);
    }

    /** @test */
    public function button_player_folds_their_hand()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();

        /** @var Player $player1 */
        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1); // SB - 25
        $player3 = $table->playersSatDown()->get(2); // BB - 50
        $player4 = $table->playersSatDown()->get(3); // Call - 50

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($player2);
        $round->postBigBlind($player3);

        $round->playerCalls($player4);
        $round->playerFoldsHand($player1);

        $this->assertEquals(125, $round->betStacks()->total()->amount());
        $this->assertCount(3, $round->playersStillIn());
        $this->assertFalse($round->playerIsStillIn($player1));
    }

    /** @test */
    public function can_confirm_it_is_player_after_big_blinds_turn()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1); // SB - 25
        $seat3 = $table->playersSatDown()->get(2); // BB - 50
        $seat4 = $table->playersSatDown()->get(3); // Call - 50

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $this->assertEquals($seat4, $round->whosTurnIsIt());
        $round->playerCalls($seat4);

        $this->assertEquals($seat1, $round->whosTurnIsIt());
        $round->playerFoldsHand($seat1);

        $this->assertEquals($seat2, $round->whosTurnIsIt());
        $round->playerCalls($seat2);

        $this->assertEquals($seat3, $round->whosTurnIsIt());
        $round->playerCalls($seat3);

        // no one else has to action
        $this->assertEquals(false, $round->whosTurnIsIt());
    }

    /** @test */
    public function can_confirm_whos_turn_it_is_with_all_ins()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1); // SB - 25
        $seat3 = $table->playersSatDown()->get(2); // BB - 50
        $seat4 = $table->playersSatDown()->get(3); // Call - 50

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $this->assertEquals($seat4, $round->whosTurnIsIt());
        $round->playerPushesAllIn($seat4);

        $this->assertEquals($seat1, $round->whosTurnIsIt());
        $round->playerFoldsHand($seat1);

        $this->assertEquals($seat2, $round->whosTurnIsIt());
        $round->playerPushesAllIn($seat2);

        $this->assertEquals($seat3, $round->whosTurnIsIt());
        $round->playerFoldsHand($seat3);

        // no one else has to action
        $this->assertEquals(false, $round->whosTurnIsIt());
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test */
    public function when_no_player_left_to_act_throw_exception_when_checkPlayerTryingToAct()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1); // SB - 25
        $seat3 = $table->playersSatDown()->get(2); // BB - 50
        $seat4 = $table->playersSatDown()->get(3); // Call - 50

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $this->assertEquals($seat4, $round->whosTurnIsIt());
        $round->playerPushesAllIn($seat4);

        $this->assertEquals($seat1, $round->whosTurnIsIt());
        $round->playerFoldsHand($seat1);

        $this->assertEquals($seat2, $round->whosTurnIsIt());
        $round->playerPushesAllIn($seat2);

        $this->assertEquals($seat3, $round->whosTurnIsIt());
        $round->playerFoldsHand($seat3);

        // no one else has to action
        $round->checkPlayerTryingToAct($seat4);
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_flop_whilst_players_still_have_to_act()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->dealFlop();
    }

    /** @test */
    public function a_round_has_a_flop()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();
        $this->assertCount(1, $round->dealer()->burnCards());
        $this->assertCount(3, $round->dealer()->communityCards());
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_flop_more_than_once_a_round()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();
        $round->dealFlop();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_turn_before_the_flop()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->dealTurn();
    }

    /** @test */
    public function a_round_has_a_turn()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_turn_more_than_once_per_round()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();
        $round->dealTurn();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_turn_when_players_have_still_to_act()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);

        $round->dealTurn();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_river_before_flop_or_turn()
    {
        $game = $this->createGenericGame(4);

        /** @var Table $table */
        $table = $game->tables()->first();

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->dealRiver();
    }

    /** @test */
    public function a_round_has_a_river()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealRiver();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_river_more_than_once_per_round()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealRiver();
        $round->dealRiver();
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function cant_deal_the_river_when_players_have_still_to_act()
    {
        $game = $this->createGenericGame(6);

        /** @var Table $table */
        $table = $game->tables()->first();
        $seat1 = $table->playersSatDown()->get(0);
        $seat2 = $table->playersSatDown()->get(1);
        $seat3 = $table->playersSatDown()->get(2);
        $seat4 = $table->playersSatDown()->get(3);
        $seat5 = $table->playersSatDown()->get(4);
        $seat6 = $table->playersSatDown()->get(5);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        $round->dealHands();

        $round->postSmallBlind($seat2);
        $round->postBigBlind($seat3);

        $round->playerCalls($seat4);
        $round->playerCalls($seat5);
        $round->playerCalls($seat6);
        $round->playerCalls($seat1);
        $round->playerCalls($seat2);
        $round->playerChecks($seat3);

        $round->dealFlop();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);
        $round->playerChecks($seat1);

        $round->dealTurn();

        $round->playerChecks($seat2);
        $round->playerChecks($seat3);
        $round->playerChecks($seat4);
        $round->playerChecks($seat5);
        $round->playerChecks($seat6);

        $round->dealRiver();
    }

    /** @test */
    public function can_get_a_list_of_actions()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        // deal some hands
        $round->dealHands();

        $round->postSmallBlind($player2); // 25
        $round->postBigBlind($player3); // 50

        $round->playerCalls($player4); // 50
        $round->playerFoldsHand($player1);
        $round->playerCalls($player2); // SB + 25
        $round->playerChecks($player3); // BB

        $round->dealFlop();

        $expected = ActionCollection::make([
            new Action($player2, Action::SMALL_BLIND, ['chips' => Chips::fromAmount(25)]),
            new Action($player3, Action::BIG_BLIND, ['chips' => Chips::fromAmount(50)]),
            new Action($player4, Action::CALL, ['chips' => Chips::fromAmount(50)]),
            new Action($player1, Action::FOLD),
            new Action($player2, Action::CALL, ['chips' => Chips::fromAmount(25)]),
            new Action($player3, Action::CHECK),
            new Action($round->dealer(), Action::DEALT_FLOP, [
                'communityCards' => $round->dealer()->communityCards()->only(range(0, 3)),
            ]),
        ]);
        $this->assertEquals($expected, $round->actions());
    }

    /** @test */
    public function a_round_can_be_completed()
    {
        $game = $this->createGenericGame(4);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->first();
        $player2 = $table->playersSatDown()->get(1);
        $player3 = $table->playersSatDown()->get(2);
        $player4 = $table->playersSatDown()->get(3);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        // deal some hands
        $round->dealHands();

        // make sure we start with no chips on the table
        $this->assertEquals(0, $round->betStacksTotal());

        $round->postSmallBlind($player2); // 25
        $round->postBigBlind($player3); // 50

        $round->playerCalls($player4); // 50
        $round->playerFoldsHand($player1);
        $round->playerCalls($player2); // SB + 25
        $round->playerChecks($player3); // BB

        $this->assertEquals(150, $round->betStacksTotal());
        $this->assertCount(3, $round->playersStillIn());
        $this->assertEquals(1000, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(1)->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(2)->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(3)->chipStack()->amount());

        // collect the chips, burn a card, deal the flop
        $round->dealFlop();
        $this->assertEquals(150, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerChecks($player2); // 0
        $round->playerRaises($player3, Chips::fromAmount(250)); // 250
        $round->playerCalls($player4); // 250
        $round->playerFoldsHand($player2);

        $this->assertEquals(500, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(950, $round->players()->get(1)->chipStack()->amount());
        $this->assertEquals(700, $round->players()->get(2)->chipStack()->amount());
        $this->assertEquals(700, $round->players()->get(3)->chipStack()->amount());

        // collect chips, burn 1, deal 1
        $round->dealTurn();

        $this->assertEquals(650, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerRaises($player3, Chips::fromAmount(450)); // 450
        $round->playerCalls($player4); // 450

        $this->assertEquals(900, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(250, $round->players()->get(2)->chipStack()->amount());
        $this->assertEquals(250, $round->players()->get(3)->chipStack()->amount());

        // collect chips, burn 1, deal 1
        $round->dealRiver();
        $this->assertEquals(1550, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerPushesAllIn($player3); // 250
        $round->playerCalls($player4); // 250

        $round->end();
        $this->assertEquals(2050, $round->chipPots()->get(0)->total()->amount());
        $this->assertEquals(2050, $round->chipPots()->total()->amount());
        $this->assertEquals(0, $round->betStacksTotal());
    }

    /** @test */
    public function a_headsup_round_can_be_completed()
    {
        $game = $this->createGenericGame(2);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        // deal some hands
        $round->dealHands();

        // make sure we start with no chips on the table
        $this->assertEquals(0, $round->betStacksTotal());

        $round->postSmallBlind($player1); // 25
        $round->postBigBlind($player2); // 50
        $round->playerCalls($player1); // 25
        $round->playerChecks($player2);

        $this->assertEquals(100, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(950, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(950, $round->players()->get(1)->chipStack()->amount());

        // collect the chips, burn a card, deal the flop
        $round->dealFlop();
        $this->assertEquals(100, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerChecks($player1); // 0
        $round->playerRaises($player2, Chips::fromAmount(250)); // 250
        $round->playerCalls($player1); // 250

        $this->assertEquals(500, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(700, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(700, $round->players()->get(1)->chipStack()->amount());

        // collect chips, burn 1, deal 1
        $round->dealTurn();
        $this->assertEquals(600, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerRaises($player1, Chips::fromAmount(450)); // 450
        $round->playerCalls($player2); // 450

        $this->assertEquals(900, $round->betStacksTotal());
        $this->assertCount(2, $round->playersStillIn());
        $this->assertEquals(250, $round->players()->get(0)->chipStack()->amount());
        $this->assertEquals(250, $round->players()->get(1)->chipStack()->amount());

        // collect chips, burn 1, deal 1
        $round->dealRiver();
        $this->assertEquals(1500, $round->currentPot()->totalAmount());
        $this->assertEquals(0, $round->betStacksTotal());

        $round->playerChecks($player1); // 0
        $round->playerPushesAllIn($player2); // 250
        $round->playerCalls($player1); // 250

        $round->collectChipTotal();
        $this->assertEquals(2000, $round->currentPot()->totalAmount());
        $round->end();
        $this->assertEquals(0, $round->betStacksTotal());
    }

    /** @test */
    public function ending_the_round_after_the_flop_has_been_dealt_gets_turn_and_river_deal_automatically()
    {
        $game = $this->createGenericGame(2);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        // deal some hands
        $round->dealHands();

        $round->postSmallBlind($player1); // 25
        $round->postBigBlind($player2); // 50
        $round->playerCalls($player1); // 25
        $round->playerChecks($player2);

        $round->dealFlop();
        $this->assertCount(3, $round->dealer()->communityCards());

        $round->end();
        $this->assertCount(5, $round->dealer()->communityCards());
    }

    /** @test */
    public function ending_the_round_after_the_turn_has_been_dealt_gets_river_deal_automatically()
    {
        $game = $this->createGenericGame(2);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        // deal some hands
        $round->dealHands();

        $round->postSmallBlind($player1); // 25
        $round->postBigBlind($player2); // 50
        $round->playerCalls($player1); // 25
        $round->playerChecks($player2);

        $round->dealFlop();
        $this->assertCount(3, $round->dealer()->communityCards());

        $round->playerChecks($player1);
        $round->playerChecks($player2);

        $round->dealTurn();
        $this->assertCount(4, $round->dealer()->communityCards());

        $round->end();
        $this->assertCount(5, $round->dealer()->communityCards());
    }

    /**
     * @expectedException Cysha\Casino\Holdem\Exceptions\RoundException
     * @test
     */
    public function player_cant_call_out_of_turn()
    {
        $game = $this->createGenericGame(2);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        // deal some hands
        $round->dealHands();

        $round->postSmallBlind($player1); // 25
        $round->postBigBlind($player2); // 50
        $round->playerChecks($player2); // 50
    }

    /** @test */
    public function automatically_end_game_when_no_player_actions_left()
    {
        $game = $this->createGenericGame(2);

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(50), null, 9, Chips::fromAmount(500));

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        // deal some hands
        $round->dealHands();

        // make sure we start with no chips on the table
        $this->assertEquals(0, $round->betStacksTotal());

        $round->postSmallBlind($player1); // 25
        $round->postBigBlind($player2); // 50

        $round->playerRaises($player1, Chips::fromAmount(100)); // 100
        $round->playerCalls($player2); // 100

        // collect the chips, burn a card, deal the flop
        $round->dealFlop();

        $round->playerChecks($player1); // 0
        $round->playerPushesAllIn($player2); // 900
        $round->playerCalls($player1); // 900

        $this->assertFalse($round->whosTurnIsIt());

        // collect chips, burn 1, deal 1
        $round->dealTurn();

        $this->assertFalse($round->whosTurnIsIt());
    }

    /** @test */
    public function can_call_all_in_with_less_chips()
    {
        $xLink = Client::register(1, 'xLink', Chips::fromAmount(5500));
        $jesus = Client::register(2, 'jesus', Chips::fromAmount(5500));

        $gameRules = new CashGameParameters(Uuid::uuid4(), Chips::fromAmount(2), null, 9, Chips::fromAmount(500));

        // we got a game
        $game = CashGame::setUp(Uuid::uuid4(), 'Demo Cash Game', $gameRules);

        // register clients to game
        $game->registerPlayer($xLink, Chips::fromAmount(44)); // x
        $game->registerPlayer($jesus, Chips::fromAmount(11)); //

        $game->assignPlayersToTables(); // table has max of 9 or 5 players in holdem

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);
        $player2 = $table->playersSatDown()->get(1);

        $round = Round::start(Uuid::uuid4(), $table, $gameRules);

        // deal some hands
        $round->dealHands();

        // make sure we start with no chips on the table
        $this->assertEquals(0, $round->betStacksTotal());

        $round->postSmallBlind($player1); // 1
        $round->postBigBlind($player2); // 2

        $round->playerPushesAllIn($player1); // SB + 43
        $round->playerCalls($player2); // BB + 9

        // collect the chips, burn a card, deal the flop
        $round->end();
        $this->assertEquals(55, $round->chipPots()->total()->amount());
    }

    /** @test */
    public function can_remove_a_player_from_table_and_game()
    {
        $game = $this->createGenericGame(4);

        $game->assignPlayersToTables();

        $table = $game->tables()->first();

        $player1 = $table->playersSatDown()->get(0);

        $game->removePlayer($player1);

        $this->assertEquals(3, $table->players()->count());
        $this->assertEquals(3, $game->players()->count());
    }
}
