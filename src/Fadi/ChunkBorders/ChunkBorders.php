<?php

namespace Fadi\ChunkBorders;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\BlockActorDataPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;

class ChunkBorders extends PluginBase
{
    /**
     * @var Position[] A list of players currently viewing chunk borders.
     */
    private array $viewers = [];

    protected function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents(new ChunkBordersListener($this), $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command can only be executed by a player.");
            return true;
        }

        if ($command->getName() === "chunkborders") {
            $this->setViewingChunkBorders($sender, !$this->isViewingChunkBorders($sender));
            $isViewing = $this->isViewingChunkBorders($sender);
            $sender->sendMessage(($isViewing ? TextFormat::GREEN : TextFormat::RED) . "You are " . ($isViewing ? "now" : "no longer") . " viewing chunk borders.");
        }

        return true;
    }

    /**
     * Check if a player is currently viewing chunk borders.
     *
     * @param Player $player
     * @return bool
     */
    public function isViewingChunkBorders(Player $player): bool
    {
        return isset($this->viewers[$player->getId()]);
    }

    /**
     * Set whether or not a player can see chunk borders.
     *
     * @param Player $player
     * @param bool   $viewing
     */
    public function setViewingChunkBorders(Player $player, bool $viewing = true): void
    {
        if (!$viewing) {
            $this->removeChunkBorderFrom($player);
            return;
        }

        $this->updateChunkBordersFor($player);
    }

    /**
     * Removes the chunk border from a player.
     *
     * @param Player $player
     */
    public function removeChunkBorderFrom(Player $player): void
    {
        $tilePos = $this->viewers[$player->getId()] ?? null;
        if ($tilePos !== null) {
            $this->sendFakeStructureBlock($player, $tilePos);
            foreach ($player->getWorld()->createBlockUpdatePackets([$tilePos]) as $pk) {
                $player->getNetworkSession()->sendDataPacket($pk);
            }
        }
        unset($this->viewers[$player->getId()]);
    }

    /**
     * Send a fake block to a specific player.
     *
     * @param Player  $player
     * @param Vector3 $position
     */
    private function sendFakeStructureBlock(Player $player, Vector3 $position): void
    {
        $blockPos = BlockPosition::fromVector3($position);
        $pk = UpdateBlockPacket::create($blockPos, TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId(StringToItemParser::getInstance()->parse("structure_block")->getBlock()->getStateId()), UpdateBlockPacket::FLAG_NETWORK, UpdateBlockPacket::DATA_LAYER_NORMAL);
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    /**
     * Updates the chunk borders for a specific player.
     *
     * @param Player $player
     */
    public function updateChunkBordersFor(Player $player): void
    {
        $this->removeChunkBorderFrom($player);

        $chunk = $player->getWorld()->getOrLoadChunkAtPosition($player->getPosition());
        $chunkX = $player->getPosition()->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $chunkZ = $player->getPosition()->getFloorZ() >> Chunk::COORD_BIT_SIZE;

        if ($chunk !== null) {
            $position = new Position($chunkX << Chunk::COORD_BIT_SIZE, -1, $chunkZ << Chunk::COORD_BIT_SIZE, $player->getWorld());

            $this->sendFakeStructureBlock($player, $position);
            $this->sendStructureBlockTile([$player], $position);

            $this->viewers[$player->getId()] = $position;
        }
    }

    /**
     * Sends the structure block tile to a player.
     *
     * @param Player[] $players
     * @param Vector3  $position
     */
    private function sendStructureBlockTile(array $players, Vector3 $position): void
    {
        $nbt = new CompoundTag();
        $nbt->setString("id", "StructureBlock");
        $nbt->setInt("x", $position->getFloorX());
        $nbt->setInt("y", $position->getFloorX());
        $nbt->setInt("z", $position->getFloorX());
        $nbt->setByte("isMovable", 0);
        $nbt->setByte("isPowered", 1);
        $nbt->setInt("data", StructureEditorData::TYPE_EXPORT);
        $nbt->setInt("redstoneSaveMode", 0);
        $nbt->setInt("xStructureOffset", 0);
        $nbt->setInt("yStructureOffset", 0);
        $nbt->setInt("zStructureOffset", 0);
        $nbt->setInt("xStructureSize", 16);
        $nbt->setInt("yStructureSize", 320);
        $nbt->setInt("zStructureSize", 16);
        $nbt->setString("structureName", "Chunk Border");
        $nbt->setString("dataField", "");
        $nbt->setByte("ignoreEntities", 0);
        $nbt->setByte("includePlayers", 1);
        $nbt->setByte("removeBlocks", 0);
        $nbt->setByte("showBoundingBox", 1);
        $nbt->setByte("rotation", 0);
        $nbt->setByte("mirror", 0);
        $nbt->setByte("animationMode", 0);
        $nbt->setFloat("animationSeconds", 0);
        $nbt->setFloat("integrity", 1.0);
        $nbt->setLong("seed", 0);

        $blockPos = BlockPosition::fromVector3($position);
        $cacheableNbt = new CacheableNbt($nbt);
        $pk = BlockActorDataPacket::create($blockPos, $cacheableNbt);

        foreach ($players as $player) {
            if ($player instanceof Player) {
                $player->getNetworkSession()->sendDataPacket($pk);
            }
        }
    }
}
