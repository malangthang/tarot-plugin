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
        $interpreter = new self();

        switch ($spread_type) {
            case '1card':
                return $interpreter->interpret_single_card($cards[0], $question);

            case '3card':
                return $interpreter->interpret_three_card($cards, $question);

            case 'celtic-cross':
                return $interpreter->interpret_celtic_cross($cards, $question);

            case 'horseshoe':
                return $interpreter->interpret_horseshoe($cards, $question);

            case 'love-spread':
                return $interpreter->interpret_love_spread($cards, $question);

            case 'career-spread':
                return $interpreter->interpret_career_spread($cards, $question);

            default:
                return $interpreter->interpret_three_card($cards, $question);
        }
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
     * 3 Card: Past-Present-Future with synthesis
     */
    private function interpret_three_card($cards, $question) {
        $past = $this->get_meaning($cards[0]);
        $present = $this->get_meaning($cards[1]);
        $future = $this->get_meaning($cards[2]);

        $past_card = $cards[0]['card']['name'];
        $present_card = $cards[1]['card']['name'];
        $future_card = $cards[2]['card']['name'];

        // Synthesize a narrative
        $narrative = "Your reading begins with <strong>$past_card</strong> in the past position, suggesting foundation of " . $this->extract_keyword($past) . ". ";
        $narrative .= "Currently, <strong>$present_card</strong> shows " . $this->extract_keyword($present) . " in your situation. ";
        $narrative .= "Looking ahead, <strong>$future_card</strong> indicates " . $this->extract_keyword($future) . " to come.";

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
     * Love Spread: 5-card relationship reading
     */
    private function interpret_love_spread($cards, $question) {
        $positions = ['Me', 'The Other Person', 'The Relationship', 'Challenges', 'Outcome'];

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

        $narrative = "In matters of the heart, your reading reveals: ";
        $narrative .= "You are embodied by " . $cards[0]['card']['name'] . " while the other brings " . $cards[1]['card']['name'] . ". ";
        $narrative .= "The relationship is influenced by " . $cards[2]['card']['name'] . ", leading to " . $cards[4]['card']['name'] . ".";

        return [
            'title' => 'Love Reading',
            'summary' => "Your question: <strong>\"$question\"</strong>",
            'cards_display' => $cards_display,
            'interpretation' => $narrative
        ];
    }

    /**
     * Career Spread: 5-card career reading
     */
    private function interpret_career_spread($cards, $question) {
        $positions = ['Your Skills', 'Current Role', 'Environment', 'Challenge', 'Opportunity'];

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

        $narrative = "Your career path reveals: ";
        $narrative .= "Your skills shine with " . $cards[0]['card']['name'] . " in your current role as " . $cards[1]['card']['name'] . ". ";
        $narrative .= "The environment presents " . $cards[3]['card']['name'] . " but offers opportunity through " . $cards[4]['card']['name'] . ".";

        return [
            'title' => 'Career Reading',
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
     * Extract first meaningful keyword from meaning text
     */
    private function extract_keyword($meaning) {
        $words = explode(' ', $meaning);
        return strtolower($words[0]);
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