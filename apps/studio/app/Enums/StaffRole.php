<?php

namespace App\Enums;

enum StaffRole: string
{
    case Owner = 'owner';
    case Photographer = 'photographer';
    case SecondShooter = 'second_shooter';
    case Editor = 'editor';
    case Assistant = 'assistant';
}
