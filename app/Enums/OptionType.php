<?php

namespace App\Enums;

enum OptionType: string
{
    case Select = 'select';
    case Number = 'number';
    case Boolean = 'boolean';
}
