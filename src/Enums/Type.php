<?php

namespace VanOns\FilamentRedirects\Enums;

enum Type: string
{
    case Static = 'static';
    case Match = 'match';
    case Replace = 'replace';
}
