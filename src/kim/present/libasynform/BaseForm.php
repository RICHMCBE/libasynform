<?php

/**
 *
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the MIT License. see <https://opensource.org/licenses/MIT>.
 *
 * @author       PresentKim (debe3721@gmail.com)
 * @link         https://github.com/PresentKim
 * @license      https://opensource.org/licenses/MIT MIT License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace kim\present\libasynform;

use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;
use SOFe\AwaitGenerator\Await;

use function spl_object_id;

abstract class BaseForm implements Form{

    abstract public static function getType() : string;

    abstract protected function processData(mixed $data) : mixed;

    /**
     * @var \Closure[] $promises
     * @phpstan-var array<int, array{\Closure, \Closure}>
     */
    private array $promises = [];

    protected array $data;

    public function __construct(
        string $title = ""
    ){
        $this->data = [
            "type" => static::getType(),
            "title" => $title
        ];
    }

    public function getTitle() : string{
        return $this->data["title"];
    }

    public function setTitle(string $title) : self{
        $this->data["title"] = $title;
        return $this;
    }

    public function handleResponse(Player $player, $data) : void{
        $id = spl_object_id($player);
        if(!isset($this->promises[$id])){
            throw new FormValidationException("Received response from player who didn't receive the form");
        }
        [$resolve, $reject] = $this->promises[$id];
        unset($this->promises[$id]);

        if($data === null){
            $resolve(null);
            return;
        }

        try{
            $data = $this->processData($data);
        }catch(\Exception $e){
            $reject($e);
            return;
        }

        $resolve($data);
    }

    public function send(Player $player) : \Generator{
        $recieve = null;
        yield from Await::promise(function($resolve, $reject) use (&$recieve, $player){
            $id = spl_object_id($player);
            if(isset($this->promises[$id])){
                $reject(new FormValidationException("Player is already viewing a form"));
                return;
            }

            $player->sendForm($this);
            $this->promises[$id] = [
                function(mixed $recive) use (&$recieve, $resolve){
                    $recieve = $recive;
                    $resolve();
                }, $reject
            ];
        });
        return $recieve;
    }

    public function jsonSerialize() : array{
        return $this->data;
    }
}
