<?php

namespace Cysha\Casino\Holdem\Game\Parameters;

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

    public function __construct(Uuid $gameId, Chips $bigBlind, Chips $smallBlind = null, int $tableSize = 9)
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

        $this->gameId = $gameId;
        $this->bigBlind = $bigBlind;
        $this->smallBlind = $smallBlind;
        $this->tableSize = $tableSize;
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
            'tableSize' => $this->tableSize
        ];
    }
}
