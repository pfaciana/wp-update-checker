<?php

namespace PackageUpgrader\V1\Context\Types;

enum Kind: string
{
	case PLUGIN = 'Plugin';
	case THEME = 'Theme';
}
