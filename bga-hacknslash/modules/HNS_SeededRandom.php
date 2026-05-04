<?php

final class HNS_SeededRandom
{
    private int $state;

    public function __construct(int $state)
    {
        $this->state = $state & 0x7fffffff;
    }

    /** @param array<int, mixed> $items */
    public function pick(array $items): mixed
    {
        return $items[$this->nextInt(count($items))];
    }

    /** @param array<int, mixed> $items */
    public function shuffle(array $items): array
    {
        for ($index = count($items) - 1; $index > 0; $index--) {
            $swapIndex = $this->nextInt($index + 1);
            [$items[$index], $items[$swapIndex]] = [$items[$swapIndex], $items[$index]];
        }

        return $items;
    }

    private function nextInt(int $max): int
    {
        if ($max <= 0) {
            throw new InvalidArgumentException('Random max must be positive.');
        }

        $this->state = (int) (($this->state * 1103515245 + 12345) & 0x7fffffff);

        return $this->state % $max;
    }
}
