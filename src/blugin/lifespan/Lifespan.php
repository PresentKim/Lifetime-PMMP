<?php

/*
 *
 *  ____  _             _         _____
 * | __ )| |_   _  __ _(_)_ __   |_   _|__  __ _ _ __ ___
 * |  _ \| | | | |/ _` | | '_ \    | |/ _ \/ _` | '_ ` _ \
 * | |_) | | |_| | (_| | | | | |   | |  __/ (_| | | | | | |
 * |____/|_|\__,_|\__, |_|_| |_|   |_|\___|\__,_|_| |_| |_|
 *                |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  Blugin team
 * @link    https://github.com/Blugin
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 */

declare(strict_types=1);

namespace blugin\lifespan;

use blugin\lib\command\BaseCommandTrait;
use blugin\lib\command\listener\AvaliableCommandListener;
use blugin\lib\translator\traits\TranslatorHolderTrait;
use blugin\lib\translator\TranslatorHolder;
use blugin\lifespan\command\overload\LifespanOverload;
use blugin\traits\singleton\SingletonTrait;
use blugin\utils\arrays\ArrayUtil as Arr;
use pocketmine\plugin\PluginBase;

class Lifespan extends PluginBase implements TranslatorHolder{
    use TranslatorHolderTrait, BaseCommandTrait, SingletonTrait;

    public const ITEM = "item";
    public const ARROW = "arrow";

    public const DEFAULTS = [
        self::ITEM => 6000,
        self::ARROW => 1200
    ];

    /** @var int[] (short) */
    private $lifespanMap;

    public function onLoad() : void{
        self::$instance = $this;

        $this->loadLanguage();
        $this->getBaseCommand();
    }

    public function onEnable() : void{
        //Register main command with subcommands
        $command = $this->getBaseCommand();
        foreach(self::DEFAULTS as $mode => $lifespan){
            $command->addOverload(new LifespanOverload($command, $mode));
        }
        $this->getServer()->getCommandMap()->register($this->getName(), $command);

        //Load lifespan data
        $dataPath = "{$this->getDataFolder()}lifespan.yml";
        if(!is_file($dataPath) || ($content = file_get_contents($dataPath)) === false){
            $this->lifespanMap = self::DEFAULTS;
            return;
        }

        $data = yaml_parse($content);
        if(!is_array($data) || Arr::validate(self::DEFAULTS, function(string $mode) use ($data){ return !is_numeric($data[$mode] ?? null); })){
            throw new \RuntimeException("Invalid data in lifespan.json file. Must be int array");
        }
        foreach($data as $mode => $lifespan){
            $this->setLifespan((string) $mode, (int) $lifespan);
        }

        //Register event listeners
        AvaliableCommandListener::register($this);
        EntitySpawnListener::register($this);
    }

    public function onDisable() : void{
        //Unregister main command with subcommands
        $this->getServer()->getCommandMap()->unregister($this->getBaseCommand());

        //Save lifespan data
        if(is_array($this->lifespanMap)){
            yaml_emit_file("{$this->getDataFolder()}lifespan.yml", ($this->lifespanMap));
        }
    }

    public function getLifespan(string $mode) : ?int{
        return $this->lifespanMap[$mode] ?? null;
    }

    public function setLifespan(string $mode, int $value) : void{
        if(!isset(self::DEFAULTS[$mode]))
            throw new \InvalidArgumentException("Mode '$mode' is invalid");

        if($value < 0)
            throw new \InvalidArgumentException("Value $value is too small, it must be at least 0");

        if($value > ($max = 0x8000 + self::DEFAULTS[$mode]))
            throw new \InvalidArgumentException("Value $value is too big, it must be at most $max");

        $this->lifespanMap[$mode] = $value;
    }
}
