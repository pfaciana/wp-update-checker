<?php

namespace PackageUpgrader\V1\Context\Types;

class GitLabAsset
{
	public int $count;
	/** @var GitLabSource[] */
	public array $sources;
	/** @var GitLabLink[] */
	public array $links;
}