<?php
namespace PhpGen\Http\Controllers;

use PhpGen\Database\Introspector;

class DashboardController
{
    public function index(): void
    {
        global $twig;
        $db = new Introspector();
        $data = $db->getDashboardStats();

        $chartData = [];
        foreach ($data['stats'] as $s) {
            if (!empty($s['chart'])) {
                $chartData[$s['name']] = $s['chart'];
            }
        }

        echo $twig->render('dashboard.twig', [
            'stats' => $data['stats'],
            'statsJson' => jsonSafe($data['stats']),
            'chartJson' => jsonSafe($chartData),
            'top5Json' => jsonSafe($data['top5']),
            'timelineJson' => jsonSafe($data['timeline']),
            'mainTable' => $data['mainTable'],
        ]);
    }
}