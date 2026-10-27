<?php
namespace PhpGen\Jobs;

use PhpGen\Database\Introspector;

class WebhookProcessor
{
    private Introspector $db;

    public function __construct(Introspector $db)
    {
        $this->db = $db;
    }

    public function process(int $limit = 20): array
    {
        return $this->db->processWebhookQueue($limit);
    }
}