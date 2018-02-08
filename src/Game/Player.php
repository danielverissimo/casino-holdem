<?php

namespace Cysha\Casino\Holdem\Game;

use Cysha\Casino\Game\Chips;
use Cysha\Casino\Game\Client;
use Cysha\Casino\Game\Contracts\Player as PlayerContract;
use JsonSerializable;

class Player extends Client implements PlayerContract, JsonSerializable
{
    /**
     * @var Chips
     */
    private $chipStack;

    /**
     * @var Chips
     */
    private $stackWin;

    /**
     * @var int
     */
    private $originalPosition;

    /**
     * PlayerTest constructor.
     *
     * @param string $name
     * @param Chips  $chips
     */
    public function __construct(int $id, $name, Chips $wallet = null, Chips $chips = null)
    {
        parent::__construct($id, $name, $wallet);

        $this->chipStack = $chips ?? Chips::zero();
        $this->stackWin = Chips::zero();
    }

    /**
     * @param Client $client
     * @param Chips  $chipCount
     *
     * @return PlayerContract
     */
    public static function fromClient(Client $client, Chips $chipCount = null): PlayerContract
    {
        return new self($client->id(), $client->name(), $client->wallet(), $chipCount);
    }

    /**
     * @param PlayerContract $object
     *
     * @return bool
     */
    public function equals(PlayerContract $object): bool
    {
        return static::class === get_class($object)
        && $this->id() === $object->id()
        && $this->name() === $object->name();
//        && $this->wallet() === $object->wallet()
//        && $this->chipStack() === $object->chipStack();
    }

    /**
     * @return Chips
     */
    public function chipStack(): Chips
    {
        return $this->chipStack;
    }

    public function setChipStack($chipStack)
    {
        $this->chipStack = $chipStack;
    }

    /**
     * @return Chips
     */
    public function stackWin(): Chips
    {
        return $this->stackWin;
    }

    /**
     * @return int
     */
    public function originalPosition(): int
    {
        return $this->originalPosition;
    }

    /**
     * @param $originalPosition
     */
    public function setOriginalPosition($originalPosition)
    {
        $this->originalPosition = $originalPosition;
    }

    /**
     * @param Chips $chips
     */
    public function bet(Chips $chips)
    {
        $this->chipStack()->subtract($chips);
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'chipStack' => $this->chipStack()->jsonSerialize(),
            'stackWin' => $this->stackWin()->jsonSerialize()
        ];
    }
}
