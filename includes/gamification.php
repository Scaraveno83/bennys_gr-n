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
            'tasks'          => 1,
            'tasks_xp_cap'   => 400,
            'forum_posts'    => 5,
            'news_reactions' => 3,
        ];
    }
}

if (!function_exists('gamification_levels')) {
    function gamification_levels(): array
    {
        return [
            ['xp' =>       0, 'title' => 'Newcomer'],
            ['xp' =>     150, 'title' => 'Team Player'],
            ['xp' =>     621, 'title' => 'Werkstatt-Profi'],
            ['xp' =>    1426, 'title' => 'Community Champ'],
            ['xp' =>    2572, 'title' => 'Werkstatt-Legende'],
            ['xp' =>    4064, 'title' => 'Innovations-Guru'],
            ['xp' =>    5906, 'title' => 'Prozess-Optimierer'],
            ['xp' =>    8101, 'title' => 'Qualitäts-Champion'],
            ['xp' =>   10652, 'title' => 'Produktions-Mentor'],
            ['xp' =>   13561, 'title' => 'Werkstatt-Ikone'],
            ['xp' =>   16830, 'title' => 'Branchen-Vorreiter'],
            ['xp' =>   20462, 'title' => 'Werkstatt-Mythos'],
            ['xp' =>   24458, 'title' => 'Werkstatt-Praktiker III'],
            ['xp' =>   28819, 'title' => 'Werkstatt-Praktiker IV'],
            ['xp' =>   33547, 'title' => 'Werkstatt-Praktiker V'],
            ['xp' =>   38644, 'title' => 'Werkstatt-Praktiker VI'],
            ['xp' =>   44110, 'title' => 'Werkstatt-Praktiker VII'],
            ['xp' =>   49947, 'title' => 'Werkstatt-Praktiker VIII'],
            ['xp' =>   56156, 'title' => 'Werkstatt-Praktiker IX'],
            ['xp' =>   62739, 'title' => 'Werkstatt-Praktiker X'],
            ['xp' =>   69695, 'title' => 'Werkstatt-Pionier I'],
            ['xp' =>   77027, 'title' => 'Werkstatt-Pionier II'],
            ['xp' =>   84734, 'title' => 'Werkstatt-Pionier III'],
            ['xp' =>   92818, 'title' => 'Werkstatt-Pionier IV'],
            ['xp' =>  101280, 'title' => 'Werkstatt-Pionier V'],
            ['xp' =>  110121, 'title' => 'Werkstatt-Pionier VI'],
            ['xp' =>  119340, 'title' => 'Werkstatt-Pionier VII'],
            ['xp' =>  128940, 'title' => 'Werkstatt-Pionier VIII'],
            ['xp' =>  138920, 'title' => 'Werkstatt-Pionier IX'],
            ['xp' =>  149282, 'title' => 'Werkstatt-Pionier X'],
            ['xp' =>  160026, 'title' => 'Werkstatt-Experte I'],
            ['xp' =>  171152, 'title' => 'Werkstatt-Experte II'],
            ['xp' =>  182662, 'title' => 'Werkstatt-Experte III'],
            ['xp' =>  194556, 'title' => 'Werkstatt-Experte IV'],
            ['xp' =>  206835, 'title' => 'Werkstatt-Experte V'],
            ['xp' =>  219498, 'title' => 'Werkstatt-Experte VI'],
            ['xp' =>  232547, 'title' => 'Werkstatt-Experte VII'],
            ['xp' =>  245983, 'title' => 'Werkstatt-Experte VIII'],
            ['xp' =>  259805, 'title' => 'Werkstatt-Experte IX'],
            ['xp' =>  274015, 'title' => 'Werkstatt-Experte X'],
            ['xp' =>  288612, 'title' => 'Werkstatt-Architekt I'],
            ['xp' =>  303597, 'title' => 'Werkstatt-Architekt II'],
            ['xp' =>  318972, 'title' => 'Werkstatt-Architekt III'],
            ['xp' =>  334735, 'title' => 'Werkstatt-Architekt IV'],
            ['xp' =>  350889, 'title' => 'Werkstatt-Architekt V'],
            ['xp' =>  367432, 'title' => 'Werkstatt-Architekt VI'],
            ['xp' =>  384366, 'title' => 'Werkstatt-Architekt VII'],
            ['xp' =>  401691, 'title' => 'Werkstatt-Architekt VIII'],
            ['xp' =>  419407, 'title' => 'Werkstatt-Architekt IX'],
            ['xp' =>  437515, 'title' => 'Werkstatt-Architekt X'],
            ['xp' =>  456016, 'title' => 'Werkstatt-Taktiker I'],
            ['xp' =>  474909, 'title' => 'Werkstatt-Taktiker II'],
            ['xp' =>  494195, 'title' => 'Werkstatt-Taktiker III'],
            ['xp' =>  513874, 'title' => 'Werkstatt-Taktiker IV'],
            ['xp' =>  533947, 'title' => 'Werkstatt-Taktiker V'],
            ['xp' =>  554415, 'title' => 'Werkstatt-Taktiker VI'],
            ['xp' =>  575277, 'title' => 'Werkstatt-Taktiker VII'],
            ['xp' =>  596533, 'title' => 'Werkstatt-Taktiker VIII'],
            ['xp' =>  618185, 'title' => 'Werkstatt-Taktiker IX'],
            ['xp' =>  640233, 'title' => 'Werkstatt-Taktiker X'],
            ['xp' =>  662676, 'title' => 'Werkstatt-Meister I'],
            ['xp' =>  685516, 'title' => 'Werkstatt-Meister II'],
            ['xp' =>  708752, 'title' => 'Werkstatt-Meister III'],
            ['xp' =>  732385, 'title' => 'Werkstatt-Meister IV'],
            ['xp' =>  756415, 'title' => 'Werkstatt-Meister V'],
            ['xp' =>  780843, 'title' => 'Werkstatt-Meister VI'],
            ['xp' =>  805668, 'title' => 'Werkstatt-Meister VII'],
            ['xp' =>  830892, 'title' => 'Werkstatt-Meister VIII'],
            ['xp' =>  856514, 'title' => 'Werkstatt-Meister IX'],
            ['xp' =>  882535, 'title' => 'Werkstatt-Meister X'],
            ['xp' =>  908955, 'title' => 'Werkstatt-Virtuose I'],
            ['xp' =>  935774, 'title' => 'Werkstatt-Virtuose II'],
            ['xp' =>  962992, 'title' => 'Werkstatt-Virtuose III'],
            ['xp' =>  990611, 'title' => 'Werkstatt-Virtuose IV'],
            ['xp' => 1018630, 'title' => 'Werkstatt-Virtuose V'],
            ['xp' => 1047049, 'title' => 'Werkstatt-Virtuose VI'],
            ['xp' => 1075868, 'title' => 'Werkstatt-Virtuose VII'],
            ['xp' => 1105089, 'title' => 'Werkstatt-Virtuose VIII'],
            ['xp' => 1134711, 'title' => 'Werkstatt-Virtuose IX'],
            ['xp' => 1164734, 'title' => 'Werkstatt-Virtuose X'],
            ['xp' => 1195159, 'title' => 'Werkstatt-Elite I'],
            ['xp' => 1225986, 'title' => 'Werkstatt-Elite II'],
            ['xp' => 1257215, 'title' => 'Werkstatt-Elite III'],
            ['xp' => 1288847, 'title' => 'Werkstatt-Elite IV'],
            ['xp' => 1320881, 'title' => 'Werkstatt-Elite V'],
            ['xp' => 1353319, 'title' => 'Werkstatt-Elite VI'],
            ['xp' => 1386159, 'title' => 'Werkstatt-Elite VII'],
            ['xp' => 1419403, 'title' => 'Werkstatt-Elite VIII'],
            ['xp' => 1453051, 'title' => 'Werkstatt-Elite IX'],
            ['xp' => 1487102, 'title' => 'Werkstatt-Elite X'],
            ['xp' => 1521558, 'title' => 'Werkstatt-Legende I'],
            ['xp' => 1556418, 'title' => 'Werkstatt-Legende II'],
            ['xp' => 1591682, 'title' => 'Werkstatt-Legende III'],
            ['xp' => 1627351, 'title' => 'Werkstatt-Legende IV'],
            ['xp' => 1663426, 'title' => 'Werkstatt-Legende V'],
            ['xp' => 1699905, 'title' => 'Werkstatt-Legende VI'],
            ['xp' => 1736790, 'title' => 'Werkstatt-Legende VII'],
            ['xp' => 1774080, 'title' => 'Werkstatt-Legende VIII'],
            ['xp' => 1811777, 'title' => 'Werkstatt-Legende IX'],
            ['xp' => 1849879, 'title' => 'Werkstatt-Legende X'],
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

            $tasksWeight = max(0.0, (float)($weights['tasks'] ?? 0));
            $xpTasksRaw  = $tasks * $tasksWeight;
            $tasksCap    = $weights['tasks_xp_cap'] ?? null;
            if (is_numeric($tasksCap)) {
                $xpTasksRaw = min($xpTasksRaw, max(0.0, (float)$tasksCap));
            }
            $xpTasks     = (int)round($xpTasksRaw);

            $xpForum     = (int)round($forumPosts * max(0.0, (float)($weights['forum_posts'] ?? 0)));
            $xpReactions = (int)round($newsReactions * max(0.0, (float)($weights['news_reactions'] ?? 0)));
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