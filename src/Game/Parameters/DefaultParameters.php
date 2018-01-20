<?php

namespace Cysha\Casino\Holdem\Game\Parameters;

use Carbon\Carbon;
use Cysha\Casino\Game\Chips;
use Cysha\Casino\Game\Contracts\GameParameters;
use Cysha\Casino\Holdem\Exceptions\GameParametersException;
use Ramsey\Uuid\Uuid;

class DefaultParameters implements GameParameters
{
    /**
     * @var Uuid
     */
    private $gameId;

    /**
     * @var Chips
     */
    private $smallBlind;

    /**
     * @var Chips
     */
    private $bigBlind;

    /**
     * @var int
     */
    private $tableSize = 9;

    /**
     * @var int
     */
    private $currentLevel = 0;

    /**
     * @var int
     */
    private $maxLevelRebuy;

    /**
     * @var String
     */
    private $gameStartedAt;

    /**
     * @var String
     */
    private $lastLevelStartedAt;

    public function __construct(Uuid $gameId, Chips $bigBlind, Chips $smallBlind = null, int $tableSize = 9, int $maxLevelRebuy = 0)
    {
        if ($tableSize < 2) {
            throw GameParametersException::invalidArgument(sprintf('Invalid tableSize given, minimum of 2 expected, received %d', $tableSize));
        }
        if ($bigBlind < $smallBlind) {
            throw GameParametersException::invalidArgument(sprintf('Invalid Blinds given, bigBlind should be >= smallBlind, received %d/%d ', $smallBlind->amount(), $bigBlind->amount()));
        }

        // if the small blind amount, manages to be zero, switch it to null
        if ($smallBlind !== null && $smallBlind->amount() === 0) {
            $smallBlind = null;
        }

        $this->lastLevelStartedAt = Carbon::now();
        $this->gameStartedAt = $this->lastLevelStartedAt;

        $this->gameId = $gameId;
        $this->bigBlind = $bigBlind;
        $this->smallBlind = $smallBlind;
        $this->tableSize = $tableSize;
        $this->maxLevelRebuy = $maxLevelRebuy;
    }

    /**
     * @return Uuid
     */
    public function gameId(): Uuid
    {
        return $this->gameId;
    }

    /**
     * @return Chips
     */
    public function bigBlind(): Chips
    {
        return $this->bigBlind;
    }

    /**
     * @return int
     */
    public function maxLevelRebuy(): int
    {
        return $this->maxLevelRebuy;
    }

    /**
     * @return mixed
     */
    public function lastLevelStartedAt()
    {
        return Carbon::parse($this->lastLevelStartedAt);
    }

    /**
     * @return mixed
     */
    public function renewLastLevelStartedAt()
    {
        $this->lastLevelStartedAt = Carbon::now();
    }

    /**
     * @return mixed
     */
    public function gameStartedAt()
    {
        return Carbon::parse($this->gameStartedAt);
    }

    /**
     * @param $smallBlind
     */
    public function setSmallBlind($smallBlind){
        $this->smallBlind = $smallBlind;
    }

    /**
     * @param $bigBlind
     */
    public function setBigBlind($bigBlind){
        $this->bigBlind = $bigBlind;
    }

    /**
     * @return int
     */
    public function currentLevel(){
        return $this->currentLevel;
    }

    /**
     * Increment current level.
     */
    public function incrementCurrentLevel(){
        $this->currentLevel++;
    }

    /**
     * @return Chips
     */
    public function smallBlind(): Chips
    {
        if ($this->smallBlind === null) {
            $bigBlindAmount = floor($this->bigBlind()->amount() / 2);
            $this->smallBlind = Chips::fromAmount($bigBlindAmount);
        }

        return $this->smallBlind;
    }

    /**
     * @return int
     */
    public function tableSize(): int
    {
        return $this->tableSize;
    }

    public function jsonSerialize()
    {
        return [
            'gameId' => $this->gameId,
            'smallBlind' => $this->smallBlind,
            'bigBlind' => $this->bigBlind,
            'tableSize' => $this->tableSize,
            'currentLevel' => $this->currentLevel,
            'maxLevelRebuy' => $this->maxLevelRebuy,
            'gameStartedAt' => $this->gameStartedAt,
            'lastLevelStartedAt' => $this->lastLevelStartedAt
        ];
    }
}