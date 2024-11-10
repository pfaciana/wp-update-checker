<?php

namespace PackageUpgrader\V1\Context\Types;

class GitHubAsset
{
	public string $url;
	public int $id;
	public string $node_id;
	public string $name;
	public string $label;
	public GitHubAuthor $uploader;
	public string $content_type;
	public string $state;
	public int $size;
	public int $download_count;
	public string $created_at;
	public string $updated_at;
	public string $browser_download_url;
}