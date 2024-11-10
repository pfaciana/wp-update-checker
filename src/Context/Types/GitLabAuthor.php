<?php

namespace PackageUpgrader\V1\Context\Types;

class GitLabAuthor
{
	public int $id;
	public string $username;
	public string $name;
	public string $state;
	public bool $locked;
	public string $avatar_url;
	public string $web_url;
}