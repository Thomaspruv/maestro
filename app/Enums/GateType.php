<?php

namespace App\Enums;

enum GateType: string
{
    case SpecsReview = 'specs_review';
    case TechReview = 'tech_review';
    case MergeReview = 'merge_review';
}
