<?php

namespace Buttress;

enum SpecialCharsMode: int
{
    case AttributeKey = 51; // ENT_HTML5 | ENT_QUOTES;
    case AttributeValue = 50; // ENT_HTML5 | ENT_COMPAT;
    case Content = 48; // ENT_HTML5;
}
