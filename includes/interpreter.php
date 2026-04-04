<?php

/**
 * Tarot Interpretation Engine
 * Rule-based system for combining card meanings based on spread type
 */

class TarotInterpreter {
    private $card_meanings = [];

    /**
     * Get synthesized interpretation based on spread type
     */
    public static function interpret($cards, $spread_type, $question) {
        global $wpdb;

        // Create cache key
        $cache_key = md5($question . $spread_type . json_encode(array_map(function($card) {
            return $card['card']['id'] . '_' . ($card['is_reversed'] ? '1' : '0');
        }, $cards)));

        // Check AI cache first
        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT result FROM {$wpdb->prefix}tarot_ai_cache WHERE hash_key = %s",
            $cache_key
        ));

        if ($cached && !empty($cached->result)) {
            $result = json_decode($cached->result, true);
            if ($result) {
                return $result;
            }
        }

        $interpreter = new self();
        $result = null;

        switch ($spread_type) {
            case '1card':
                $result = $interpreter->interpret_single_card($cards[0], $question);
                break;
            case '3card':
                $result = $interpreter->interpret_three_card($cards, $question);
                break;
            case 'celtic-cross':
                $result = $interpreter->interpret_celtic_cross($cards, $question);
                break;
            case 'horseshoe':
                $result = $interpreter->interpret_horseshoe($cards, $question);
                break;
            case 'love-spread':
                $result = $interpreter->interpret_love_spread($cards, $question);
                break;
            case 'career-spread':
                $result = $interpreter->interpret_career_spread($cards, $question);
                break;
            default:
                $result = $interpreter->interpret_three_card($cards, $question);
        }

        // Cache the result
        if ($result) {
            $wpdb->replace(
                $wpdb->prefix . 'tarot_ai_cache',
                [
                    'hash_key' => $cache_key,
                    'question' => $question,
                    'cards_data' => json_encode(array_map(function($card) {
                        return [
                            'card_id' => $card['card']['id'],
                            'is_reversed' => $card['is_reversed']
                        ];
                    }, $cards)),
                    'spread_type' => $spread_type,
                    'result' => json_encode($result)
                ]
            );
        }

        return $result;
    }

    /**
     * Single card: Yes/No interpretation
     */
    private function interpret_single_card($card, $question) {
        $meaning = $this->get_meaning($card);
        $orientation = $card['is_reversed'] ? 'Reversed' : 'Upright';
        $yes_no = $card['is_reversed'] ? 'No' : 'Yes';

        return [
            'title' => 'Single Card Reading',
            'summary' => "Your question: <strong>\"$question\"</strong>",
            'cards_display' => [
                [
                    'position' => 'Focus',
                    'card_name' => $card['card']['name'],
                    'orientation' => $orientation,
                    'meaning' => $meaning
                ]
            ],
            'interpretation' => "The " . $card['card']['name'] . " appears in a " . strtolower($orientation) . " position. <strong>Answer: $yes_no</strong><br><br>" . $meaning,
            'answer' => $yes_no
        ];
    }

    /**
     * 3 Card: Past-Present-Future with enhanced AI-like synthesis
     */
    private function interpret_three_card($cards, $question) {
        $past = $this->get_meaning($cards[0]);
        $present = $this->get_meaning($cards[1]);
        $future = $this->get_meaning($cards[2]);

        $past_card = $cards[0]['card']['name'];
        $present_card = $cards[1]['card']['name'];
        $future_card = $cards[2]['card']['name'];

        // Enhanced synthesis with context and flow
        $past_keywords = $this->extract_keywords($past, 3);
        $present_keywords = $this->extract_keywords($present, 3);
        $future_keywords = $this->extract_keywords($future, 3);

        $narrative = "Your journey begins with <strong>$past_card</strong> in the past, establishing a foundation of " . implode(' and ', $past_keywords) . ". ";
        $narrative .= "This foundation has led you to your current situation with <strong>$present_card</strong>, where you're experiencing " . implode(' and ', $present_keywords) . ". ";
        $narrative .= "Looking ahead, <strong>$future_card</strong> suggests that " . implode(' and ', $future_keywords) . " will unfold in your future.";

        // Add contextual advice based on question
        if (stripos($question, 'love') !== false || stripos($question, 'relationship') !== false) {
            $narrative .= " In matters of the heart, this progression shows ";
            if ($cards[2]['is_reversed']) {
                $narrative .= "challenges that will ultimately strengthen your emotional bonds.";
            } else {
                $narrative .= "a deepening of connection and mutual understanding.";
            }
        } elseif (stripos($question, 'career') !== false || stripos($question, 'job') !== false) {
            $narrative .= " In your professional life, this sequence indicates ";
            $narrative .= "skill development leading to meaningful opportunities ahead.";
        }

        return [
            'title' => 'Past-Present-Future Reading',
            'summary' => "Your question: <strong>\"$question\"</strong>",
            'cards_display' => [
                [
                    'position' => 'Past',
                    'card_name' => $past_card,
                    'orientation' => $cards[0]['is_reversed'] ? 'Reversed' : 'Upright',
                    'meaning' => $past
                ],
                [
                    'position' => 'Present',
                    'card_name' => $present_card,
                    'orientation' => $cards[1]['is_reversed'] ? 'Reversed' : 'Upright',
                    'meaning' => $present
                ],
                [
                    'position' => 'Future',
                    'card_name' => $future_card,
                    'orientation' => $cards[2]['is_reversed'] ? 'Reversed' : 'Upright',
                    'meaning' => $future
                ]
            ],
            'interpretation' => $narrative
        ];
    }

    /**
     * Celtic Cross: Comprehensive 10-card interpretation
     */
    private function interpret_celtic_cross($cards, $question) {
        $positions = ['Present', 'Challenge', 'Distant Past', 'Possible Outcome', 'Recent Past',
                      'Near Future', 'Approach', 'External', 'Hopes & Fears', 'Final Outcome'];

        $cards_display = [];
        $key_influences = [];

        foreach ($cards as $index => $card) {
            $meaning = $this->get_meaning($card);
            $cards_display[] = [
                'position' => $positions[$index],
                'card_name' => $card['card']['name'],
                'orientation' => $card['is_reversed'] ? 'Reversed' : 'Upright',
                'meaning' => $meaning
            ];
            $key_influences[] = $card['card']['name'];
        }

        $narrative = "This comprehensive Celtic Cross reading for your question \"$question\" reveals: ";
        $narrative .= "Your current situation centers on " . $key_influences[0] . " with " . $key_influences[1] . " as the main challenge. ";
        $narrative .= "The near future brings " . $key_influences[5] . ", leading ultimately to " . $key_influences[9] . ".";

        return [
            'title' => 'Celtic Cross Reading',
            'summary' => "Your question: <strong>\"$question\"</strong>",
            'cards_display' => $cards_display,
            'interpretation' => $narrative
        ];
    }

    /**
     * Horseshoe: 7-card guidance reading
     */
    private function interpret_horseshoe($cards, $question) {
        $positions = ['Past', 'Present', 'Future', 'Obstacles', 'External Influences', 'Hopes & Fears', 'Advice'];

        $cards_display = [];
        foreach ($cards as $index => $card) {
            $meaning = $this->get_meaning($card);
            $cards_display[] = [
                'position' => $positions[$index],
                'card_name' => $card['card']['name'],
                'orientation' => $card['is_reversed'] ? 'Reversed' : 'Upright',
                'meaning' => $meaning
            ];
        }

        $narrative = "The Horseshoe spread for \"$question\" shows: ";
        $narrative .= "From " . $cards[0]['card']['name'] . " to " . $cards[2]['card']['name'] . ", ";
        $narrative .= "you face " . $cards[3]['card']['name'] . " but receive guidance of " . $cards[6]['card']['name'] . ".";

        return [
            'title' => 'Horseshoe Reading',
            'summary' => "Your question: <strong>\"$question\"</strong>",
            'cards_display' => $cards_display,
            'interpretation' => $narrative
        ];
    }

    /**
     * Love Spread: Enhanced relationship-focused interpretation
     */
    private function interpret_love_spread($cards, $question) {
        $positions = ['Me', 'The Other Person', 'The Relationship', 'Challenges', 'Outcome'];

        $cards_display = [];
        $card_names = [];
        foreach ($cards as $index => $card) {
            $meaning = $this->get_meaning($card);
            $cards_display[] = [
                'position' => $positions[$index],
                'card_name' => $card['card']['name'],
                'orientation' => $card['is_reversed'] ? 'Reversed' : 'Upright',
                'meaning' => $meaning
            ];
            $card_names[] = $card['card']['name'];
        }

        // Enhanced love narrative
        $narrative = "In your relationship reading, you are represented by <strong>{$card_names[0]}</strong>, embodying " . implode(' and ', $this->extract_keywords($this->get_meaning($cards[0]), 2)) . ". ";
        $narrative .= "Your partner brings <strong>{$card_names[1]}</strong> to the dynamic, contributing " . implode(' and ', $this->extract_keywords($this->get_meaning($cards[1]), 2)) . ". ";
        $narrative .= "The relationship itself is characterized by <strong>{$card_names[2]}</strong>, showing " . implode(' and ', $this->extract_keywords($this->get_meaning($cards[2]), 2)) . ". ";

        // Analyze challenges and outcome
        $challenge_keywords = $this->extract_keywords($this->get_meaning($cards[3]), 2);
        $outcome_keywords = $this->extract_keywords($this->get_meaning($cards[4]), 2);

        $narrative .= "Current challenges include " . implode(' and ', $challenge_keywords) . " as shown by <strong>{$card_names[3]}</strong>. ";
        $narrative .= "The outcome, revealed through <strong>{$card_names[4]}</strong>, suggests " . implode(' and ', $outcome_keywords) . " for your romantic journey.";

        // Add specific advice based on card combinations
        if ($cards[4]['is_reversed']) {
            $narrative .= " While challenges exist, this reversed card indicates that growth and understanding can transform these obstacles into opportunities for deeper connection.";
        } else {
            $narrative .= " The upright outcome card suggests positive developments and harmonious resolution of current tensions.";
        }

        return [
            'title' => 'Love & Relationship Reading',
            'summary' => "Your question: <strong>\"$question\"</strong>",
            'cards_display' => $cards_display,
            'interpretation' => $narrative
        ];
    }

    /**
     * Career Spread: Enhanced work-focused interpretation
     */
    private function interpret_career_spread($cards, $question) {
        $positions = ['Your Skills', 'Current Role', 'Environment', 'Challenge', 'Opportunity'];

        $cards_display = [];
        $card_names = [];
        foreach ($cards as $index => $card) {
            $meaning = $this->get_meaning($card);
            $cards_display[] = [
                'position' => $positions[$index],
                'card_name' => $card['card']['name'],
                'orientation' => $card['is_reversed'] ? 'Reversed' : 'Upright',
                'meaning' => $meaning
            ];
            $card_names[] = $card['card']['name'];
        }

        // Enhanced career narrative
        $narrative = "Your career reading reveals a path of " . implode(' and ', $this->extract_keywords($this->get_meaning($cards[0]), 2)) . " through <strong>{$card_names[0]}</strong>. ";
        $narrative .= "In your current role, <strong>{$card_names[1]}</strong> indicates " . implode(' and ', $this->extract_keywords($this->get_meaning($cards[1]), 2)) . ". ";
        $narrative .= "The work environment is shaped by <strong>{$card_names[2]}</strong>, bringing " . implode(' and ', $this->extract_keywords($this->get_meaning($cards[2]), 2)) . ". ";

        // Analyze challenges and opportunities
        $challenge_keywords = $this->extract_keywords($this->get_meaning($cards[3]), 2);
        $opportunity_keywords = $this->extract_keywords($this->get_meaning($cards[4]), 2);

        $narrative .= "Current challenges include " . implode(' and ', $challenge_keywords) . " as represented by <strong>{$card_names[3]}</strong>. ";
        $narrative .= "However, <strong>{$card_names[4]}</strong> opens doors to " . implode(' and ', $opportunity_keywords) . " in your professional journey.";

        // Add career-specific advice
        if ($cards[4]['is_reversed']) {
            $narrative .= " Though opportunities may be delayed, this suggests that patience and continued skill development will eventually lead to the recognition you deserve.";
        } else {
            $narrative .= " The upright opportunity card indicates that your efforts are building toward significant professional advancement and fulfillment.";
        }

        return [
            'title' => 'Career & Work Reading',
            'summary' => "Your question: <strong>\"$question\"</strong>",
            'cards_display' => $cards_display,
            'interpretation' => $narrative
        ];
    }

    /**
     * Extract primary meaning from card data
     */
    private function get_meaning($card) {
        $is_reversed = $card['is_reversed'];
        $type = $is_reversed ? 'reversed' : 'upright';

        // Try to get general meaning first
        if (!empty($card['meanings'][$type]['general'])) {
            return $this->truncate($card['meanings'][$type]['general']['meaning'], 200);
        }

        // Fallback to any available meaning
        foreach ($card['meanings'][$type] as $context => $data) {
            if (!empty($data['meaning'])) {
                return $this->truncate($data['meaning'], 200);
            }
        }

        return "The " . $card['card']['name'] . " appears in a " . strtoupper($type) . " position.";
    }

    /**
     * Extract multiple meaningful keywords from meaning text
     */
    private function extract_keywords($meaning, $count = 3) {
        // Common tarot keywords to look for
        $tarot_keywords = [
            'love', 'relationship', 'career', 'money', 'success', 'challenge', 'opportunity',
            'change', 'growth', 'healing', 'wisdom', 'intuition', 'strength', 'courage',
            'patience', 'balance', 'harmony', 'conflict', 'decision', 'choice', 'path',
            'journey', 'beginning', 'end', 'transformation', 'renewal', 'hope', 'fear',
            'trust', 'faith', 'guidance', 'protection', 'abundance', 'lack', 'freedom',
            'responsibility', 'commitment', 'passion', 'creativity', 'communication'
        ];

        $found_keywords = [];
        $meaning_lower = strtolower($meaning);

        foreach ($tarot_keywords as $keyword) {
            if (strpos($meaning_lower, $keyword) !== false && count($found_keywords) < $count) {
                $found_keywords[] = $keyword;
            }
        }

        // If not enough keywords found, extract from text
        if (count($found_keywords) < $count) {
            $words = explode(' ', $meaning);
            foreach ($words as $word) {
                $word = trim($word, '.,!?;:"');
                if (strlen($word) > 4 && !in_array(strtolower($word), $found_keywords) && count($found_keywords) < $count) {
                    $found_keywords[] = strtolower($word);
                }
            }
        }

        return array_slice($found_keywords, 0, $count);
    }

    /**
     * Extract first meaningful keyword from meaning text (legacy method)
     */
    private function extract_keyword($meaning) {
        $keywords = $this->extract_keywords($meaning, 1);
        return $keywords[0] ?? 'guidance';
    }

    /**
     * Truncate text to word boundary
     */
    private function truncate($text, $length = 200) {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, strrpos(substr($text, 0, $length), ' ')) . '...';
    }
}