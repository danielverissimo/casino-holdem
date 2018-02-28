<?php

namespace Cysha\Casino\Holdem\Cards;

use Cysha\Casino\Cards\ResultCollection;
use JsonSerializable;

class SevenCardResultCollection extends ResultCollection implements JsonSerializable
{
    public function __toString()
    {
        return $this->map->definition()->implode("\n");
    }
}
