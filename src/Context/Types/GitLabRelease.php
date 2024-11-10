<?php

namespace PackageUpgrader\V1\Context\Types;

class GitLabRelease
{
	public string $name;
	public string $tag_name;
	public string $description;
	public string $created_at;
	public string $released_at;
	public bool $upcoming_release;
	public GitLabAuthor $author;
	public GitLabCommit $commit;
	public string $commit_path;
	public string $tag_path;
	public GitLabAsset $assets;
	public array $evidences;
	public object $_links;
}
