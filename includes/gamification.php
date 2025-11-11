<?php

declare(strict_types=1);

/**
 * Gamification-Helfer: aggregiert Aktivitätspunkte über Wochenaufgaben,
 * Forum und News-Reaktionen und bereitet Level sowie Achievements auf.
 */

if (!function_exists('gamification_normalize_name')) {
    /**
     * Normalisiert einen Namen für Vergleiche.
     */
    function gamification_normalize_name(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }
        return function_exists('mb_strtolower')
            ? mb_strtolower($trimmed, 'UTF-8')
            : strtolower($trimmed);
    }
}

if (!function_exists('gamification_table_exists')) {
    /**
     * Prüft schnell, ob eine Tabelle existiert (Ergebnis wird gecached).
     */
    function gamification_table_exists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
            $cache[$table] = true;
        } catch (Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}

if (!function_exists('gamification_weights')) {
    function gamification_weights(): array
    {
        return [
            'tasks'          => 2,
            'forum_posts'    => 5,
            'news_reactions' => 3,
        ];
    }
}

if (!function_exists('gamification_levels')) {
    function gamification_levels(): array
    {
        return [
            ['xp' =>   0, 'title' => 'Newcomer'],
            ['xp' => 150, 'title' => 'Team Player'],
            ['xp' => 400, 'title' => 'Werkstatt-Profi'],
            ['xp' => 800, 'title' => 'Community Champ'],
            ['xp' => 1300, 'title' => 'Werkstatt-Legende'],
        ];
    }
}

if (!function_exists('gamification_achievements')) {
    function gamification_achievements(): array
    {
        return [
            [
                'key'         => 'first_steps',
                'title'       => 'Erster Einsatz',
                'description' => 'Sammle mindestens 25 XP.',
                'type'        => 'xp',
                'threshold'   => 25,
            ],
            [
                'key'         => 'task_machine',
                'title'       => 'Produktions-Profi',
                'description' => '200 Teile in Wochenaufgaben abgeschlossen.',
                'type'        => 'tasks',
                'threshold'   => 200,
            ],
            [
                'key'         => 'forum_voice',
                'title'       => 'Diskussions-Starter',
                'description' => '10 Beiträge im Forum verfasst.',
                'type'        => 'forum_posts',
                'threshold'   => 10,
            ],
            [
                'key'         => 'news_messenger',
                'title'       => 'News-Influencer',
                'description' => '20 Reaktionen auf News verteilt.',
                'type'        => 'news_reactions',
                'threshold'   => 20,
            ],
            [
                'key'         => 'allrounder',
                'title'       => 'Allround-Talent',
                'description' => 'Mindestens 50 Teile, 5 Forum-Posts und 5 News-Reaktionen.',
                'type'        => 'composite',
                'conditions'  => [
                    'tasks'          => 50,
                    'forum_posts'    => 5,
                    'news_reactions' => 5,
                ],
            ],
            [
                'key'         => 'legend',
                'title'       => 'Werkstatt-Legende',
                'description' => 'Insgesamt 1.000 XP gesammelt.',
                'type'        => 'xp',
                'threshold'   => 1000,
            ],
        ];
    }
}

if (!function_exists('gamification_calculate')) {
    /**
     * Berechnet alle Gamification-Daten für Mitarbeitende.
     *
     * @return array<int, array<string, mixed>>
     */
    function gamification_calculate(PDO $pdo): array
    {
        $employees = [];
        $nameMap   = [];

        try {
            $stmt = $pdo->query('SELECT id, name, rang FROM mitarbeiter');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $name = (string)($row['name'] ?? '');
                $employees[$id] = [
                    'id'            => $id,
                    'name'          => $name,
                    'rang'          => $row['rang'] ?? null,
                    'metrics'       => [
                        'tasks'          => 0,
                        'forum_posts'    => 0,
                        'news_reactions' => 0,
                    ],
                    'xp_breakdown'  => [
                        'tasks'          => 0,
                        'forum_posts'    => 0,
                        'news_reactions' => 0,
                    ],
                    'xp'            => [
                        'total' => 0,
                    ],
                    'level'         => [
                        'number'            => 1,
                        'title'             => 'Newcomer',
                        'next_number'       => null,
                        'next_title'        => null,
                        'current_threshold' => 0,
                        'next_threshold'    => null,
                    ],
                    'progress'      => [
                        'xp_into_level' => 0,
                        'xp_needed'     => 0,
                        'percent'       => 0.0,
                    ],
                    'achievements'      => [],
                    'last_achievement'  => null,
                    'profile_path'      => 'profile.php?id=' . $id,
                ];

                $normalized = gamification_normalize_name($name);
                if ($normalized !== '') {
                    $nameMap[$normalized] = $id;
                }
            }
        } catch (Throwable $e) {
            return [];
        }

        if (!$employees) {
            return [];
        }

        foreach (['wochenaufgaben', 'wochenaufgaben_archiv'] as $table) {
            if (!gamification_table_exists($pdo, $table)) {
                continue;
            }

            try {
                $query = "SELECT mitarbeiter, SUM(menge) AS total_menge FROM {$table} GROUP BY mitarbeiter";
                $stmt = $pdo->query($query);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $nameKey = gamification_normalize_name((string)($row['mitarbeiter'] ?? ''));
                    if ($nameKey === '' || !isset($nameMap[$nameKey])) {
                        continue;
                    }
                    $id = $nameMap[$nameKey];
                    $employees[$id]['metrics']['tasks'] += (int)($row['total_menge'] ?? 0);
                }
            } catch (Throwable $e) {
                // Tabelle evtl. nicht vorhanden → überspringen
            }
        }

        if (gamification_table_exists($pdo, 'forum_posts')) {
            try {
                $stmt = $pdo->query('SELECT author_id, COUNT(*) AS post_count FROM forum_posts GROUP BY author_id');
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int)($row['author_id'] ?? 0);
                    if ($id > 0 && isset($employees[$id])) {
                        $employees[$id]['metrics']['forum_posts'] = (int)($row['post_count'] ?? 0);
                    }
                }
            } catch (Throwable $e) {
                // ignorieren
            }
        }

        if (gamification_table_exists($pdo, 'news_reactions_user')) {
            try {
                $sql = 'SELECT ua.mitarbeiter_id AS mid, COUNT(*) AS reaction_count
                        FROM news_reactions_user nru
                        JOIN user_accounts ua ON ua.id = nru.user_id
                        WHERE nru.user_id IS NOT NULL AND ua.mitarbeiter_id IS NOT NULL
                        GROUP BY ua.mitarbeiter_id';
                $stmt = $pdo->query($sql);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $id = (int)($row['mid'] ?? 0);
                    if ($id > 0 && isset($employees[$id])) {
                        $employees[$id]['metrics']['news_reactions'] = (int)($row['reaction_count'] ?? 0);
                    }
                }
            } catch (Throwable $e) {
                // ignorieren
            }
        }

        $weights            = gamification_weights();
        $levels             = gamification_levels();
        $achievementsConfig = gamification_achievements();

        foreach ($employees as $id => &$employee) {
            $tasks         = max(0, (int)$employee['metrics']['tasks']);
            $forumPosts    = max(0, (int)$employee['metrics']['forum_posts']);
            $newsReactions = max(0, (int)$employee['metrics']['news_reactions']);

            $xpTasks     = $tasks * ($weights['tasks'] ?? 0);
            $xpForum     = $forumPosts * ($weights['forum_posts'] ?? 0);
            $xpReactions = $newsReactions * ($weights['news_reactions'] ?? 0);
            $xpTotal     = $xpTasks + $xpForum + $xpReactions;

            $employee['xp_breakdown']['tasks'] = $xpTasks;
            $employee['xp_breakdown']['forum_posts'] = $xpForum;
            $employee['xp_breakdown']['news_reactions'] = $xpReactions;
            $employee['xp']['total'] = $xpTotal;

            $currentLevelIndex = 0;
            foreach ($levels as $index => $level) {
                if ($xpTotal >= $level['xp']) {
                    $currentLevelIndex = $index;
                } else {
                    break;
                }
            }

            $currentLevel = $levels[$currentLevelIndex];
            $nextLevel    = $levels[$currentLevelIndex + 1] ?? null;

            $employee['level'] = [
                'number'            => $currentLevelIndex + 1,
                'title'             => $currentLevel['title'],
                'next_number'       => $nextLevel ? $currentLevelIndex + 2 : null,
                'next_title'        => $nextLevel['title'] ?? null,
                'current_threshold' => (int)$currentLevel['xp'],
                'next_threshold'    => $nextLevel['xp'] ?? null,
            ];

            $xpIntoLevel = $xpTotal - (int)$currentLevel['xp'];
            $xpGap       = $nextLevel ? max(1, ((int)$nextLevel['xp'] - (int)$currentLevel['xp'])) : 0;
            $progress    = $nextLevel ? min(1.0, max(0.0, $xpIntoLevel / $xpGap)) : 1.0;

            $employee['progress'] = [
                'xp_into_level' => $xpIntoLevel,
                'xp_needed'     => $xpGap,
                'percent'       => $progress * 100,
            ];

            $achievements = [];
            $lastUnlocked = null;
            foreach ($achievementsConfig as $achievement) {
                $unlocked = false;
                switch ($achievement['type']) {
                    case 'tasks':
                        $unlocked = $tasks >= (int)$achievement['threshold'];
                        break;
                    case 'forum_posts':
                        $unlocked = $forumPosts >= (int)$achievement['threshold'];
                        break;
                    case 'news_reactions':
                        $unlocked = $newsReactions >= (int)$achievement['threshold'];
                        break;
                    case 'xp':
                        $unlocked = $xpTotal >= (int)$achievement['threshold'];
                        break;
                    case 'composite':
                        $unlocked = true;
                        foreach ($achievement['conditions'] as $metric => $min) {
                            $value = $employee['metrics'][$metric] ?? 0;
                            if ($value < (int)$min) {
                                $unlocked = false;
                                break;
                            }
                        }
                        break;
                }

                $entry = $achievement;
                $entry['unlocked'] = $unlocked;
                $achievements[] = $entry;
                if ($unlocked) {
                    $lastUnlocked = $entry;
                }
            }

            $employee['achievements']     = $achievements;
            $employee['last_achievement'] = $lastUnlocked;
        }
        unset($employee);

        return $employees;
    }
}

if (!function_exists('gamification_all')) {
    function gamification_all(PDO $pdo): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = gamification_calculate($pdo);
        }
        return $cache;
    }
}

if (!function_exists('gamification_get_leaderboard')) {
    function gamification_get_leaderboard(PDO $pdo, int $limit = 0): array
    {
        $data = array_values(gamification_all($pdo));
        usort($data, static function (array $a, array $b): int {
            $xpA = $a['xp']['total'] ?? 0;
            $xpB = $b['xp']['total'] ?? 0;
            if ($xpA === $xpB) {
                $tasksA = $a['metrics']['tasks'] ?? 0;
                $tasksB = $b['metrics']['tasks'] ?? 0;
                if ($tasksA === $tasksB) {
                    return strcmp((string)$a['name'], (string)$b['name']);
                }
                return $tasksB <=> $tasksA;
            }
            return $xpB <=> $xpA;
        });

        if ($limit > 0) {
            $data = array_slice($data, 0, $limit);
        }

        return $data;
    }
}

if (!function_exists('gamification_get_profile')) {
    function gamification_get_profile(PDO $pdo, int $mitarbeiterId): ?array
    {
        $all = gamification_all($pdo);
        return $all[$mitarbeiterId] ?? null;
    }
}

if (!function_exists('gamification_get_top_performer')) {
    function gamification_get_top_performer(PDO $pdo): ?array
    {
        $leaderboard = gamification_get_leaderboard($pdo, 1);
        return $leaderboard[0] ?? null;
    }
}