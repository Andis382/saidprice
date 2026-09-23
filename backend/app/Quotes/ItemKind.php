<?php

namespace App\Quotes;

enum ItemKind: string
{
    case Parts = 'PARTS';
    case Labour = 'LABOUR';
    case Other = 'OTHER';
}
