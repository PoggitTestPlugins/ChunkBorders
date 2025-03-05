<?php

namespace Fadi\ChunkBorders;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\format\Chunk;

class ChunkBordersListener implements Listener
{
    private ChunkBorders $handler;

    public function __construct(ChunkBorders $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Handles player movement to update chunk borders.
     *
     * @param PlayerMoveEvent $event
     */
    public function onPlayerMove(PlayerMoveEvent $event): void
    {
        $player = $event->getPlayer();
        if (!$this->handler->isViewingChunkBorders($player)) {
            return;
        }

        $fromPosX = $event->getFrom()->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $fromPosZ = $event->getFrom()->getFloorZ() >> Chunk::COORD_BIT_SIZE;
        $toPosX = $event->getTo()->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $toPosZ = $event->getTo()->getFloorZ() >> Chunk::COORD_BIT_SIZE;

        if ($fromPosX !== $toPosX || $fromPosZ !== $toPosZ) {
            $this->handler->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($player): void {
                $this->handler->updateChunkBordersFor($player);
            }), 1);
        }
    }
}
