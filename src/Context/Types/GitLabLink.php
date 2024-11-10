<?php

namespace PackageUpgrader\V1\Context\Types;

class GitLabLink
{
	public int $id;
	public string $name;
	public string $url;
	public string $direct_asset_url;
	public string $link_type;
}