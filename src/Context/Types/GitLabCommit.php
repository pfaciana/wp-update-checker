<?php

namespace PackageUpgrader\V1\Context\Types;

class GitLabCommit
{
	public string $id;
	public string $short_id;
	public string $created_at;
	public array $parent_ids;
	public string $title;
	public string $message;
	public string $author_name;
	public string $author_email;
	public string $authored_date;
	public string $committer_name;
	public string $committer_email;
	public string $committed_date;
	public object $trailers;
	public object $extended_trailers;
	public string $web_url;
}