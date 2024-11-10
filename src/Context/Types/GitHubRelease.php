<?php

namespace PackageUpgrader\V1\Context\Types;

class GitHubRelease
{
	public string $url;
	public string $assets_url;
	public string $upload_url;
	public string $html_url;
	public int $id;
	public GitHubAuthor $author;
	public string $node_id;
	public string $tag_name;
	public string $target_commitish;
	public string $name;
	public bool $draft;
	public bool $prerelease;
	public string $created_at;
	public string $published_at;
	/** @var GitHubAsset[] */
	public array $assets;
	public string $tarball_url;
	public string $zipball_url;
	public string $body;
}