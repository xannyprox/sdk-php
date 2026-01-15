<?php

declare(strict_types=1);

namespace LoteriasApi\Enums;

/**
 * Available lottery game types
 */
enum GameType: string
{
    case BONOLOTO = 'bonoloto';
    case EUROMILLONES = 'euromillones';
    case LOTOTURF = 'lototurf';
    case PRIMITIVA = 'primitiva';
    case GORDO = 'gordo';
    case QUINIELA = 'quiniela';
    case QUINIGOL = 'quinigol';
    case QUINTUPLE = 'quintuple';
    case NACIONAL = 'nacional';
    case EURODREAMS = 'eurodreams';
}
