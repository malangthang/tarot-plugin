# Tarot Interpretation Engine

## Overview

The Tarot Interpretation Engine provides rule-based synthesis of card meanings based on spread type. Instead of showing individual card meanings, it creates cohesive narratives that combine multiple cards.

## Architecture

### Core Class: `TarotInterpreter`

Located in `includes/interpreter.php`, provides static methods for interpretation.

## Supported Spreads

### 1. Single Card (1card)
**Purpose**: Quick yes/no answer  
**Logic**: 
- Upright = YES
- Reversed = NO
- Shows: Yes/No answer prominently

**Example Response**:
```json
{
    "title": "Single Card Reading",
    "summary": "Your question: What should I do?",
    "answer": "Yes",
    "interpretation": "The Magician appears in an upright position. Answer: Yes. This suggests..."
}
```

### 2. Past-Present-Future (3card)
**Purpose**: Timeline reading  
**Cards**: 3 (Past, Present, Future)  
**Logic**: Synthesizes narrative showing progression

**Example Narrative**:
```
Your reading begins with The Hermit in the past position, suggesting foundation of 
introspection. Currently, The Fool shows new beginnings in your situation. Looking 
ahead, The World indicates completion to come.
```

### 3. Celtic Cross (celtic-cross)
**Purpose**: Comprehensive life reading  
**Cards**: 10  
**Positions**:
1. Present - Current situation
2. Challenge - Main obstacle
3. Distant Past - Foundational events
4. Possible Outcome - If trends continue
5. Recent Past - Recent events
6. Near Future - Immediate future
7. Approach - How to handle it
8. External - External influences
9. Hopes & Fears - Your feelings
10. Final Outcome - Ultimate resolution

### 4. Horseshoe (horseshoe)
**Purpose**: Balanced 7-card guidance  
**Cards**: 7  
**Positions**:
1. Past - Foundation
2. Present - Current state
3. Future - Outcome
4. Obstacles - Challenges
5. External - Outside influences
6. Hopes & Fears - Inner feelings
7. Advice - Recommended action

### 5. Love Spread (love-spread)
**Purpose**: Relationship guidance  
**Cards**: 5  
**Positions**:
1. Me - Your energy
2. The Other Person - Their energy
3. The Relationship - Connection
4. Challenges - Obstacles
5. Outcome - Direction

### 6. Career Spread (career-spread)
**Purpose**: Career guidance  
**Cards**: 5  
**Positions**:
1. Your Skills - What you bring
2. Current Role - Your job
3. Environment - Workplace context
4. Challenge - Main obstacle
5. Opportunity - Growth direction

## API Response Format

### Simplified Card Response
```json
{
    "card": "The Fool",
    "is_reversed": true
}
```

### Complete Reading Response
```json
{
    "reading_id": 123,
    "spread_type": "3card",
    "spread": {
        "name": "Past, Present, Future",
        "total_cards": 3
    },
    "question": "What will happen next?",
    "cards": [
        {"card": "The Hermit", "is_reversed": false},
        {"card": "The Fool", "is_reversed": false},
        {"card": "The World", "is_reversed": true}
    ],
    "interpretation": {
        "title": "Past-Present-Future Reading",
        "summary": "Your question: What will happen next?",
        "interpretation": "Your reading begins with...",
        "cards_display": [
            {
                "position": "Past",
                "card_name": "The Hermit",
                "orientation": "Upright",
                "meaning": "..."
            }
        ]
    }
}
```

## Interpretation Logic

### Data Flow

1. **Draw Cards** → Random selection without duplicates
2. **Assign Orientation** → 50/50 upright/reversed
3. **Extract Meanings** → Get contextual meanings from database
4. **Synthesize** → Create narrative based on spread type
5. **Return** → API sends back simplified cards + interpretation

### Meaning Extraction

For each card:
1. Try to get general upright/reversed meaning
2. Fallback to any available contextual meaning (love, career, finance, health)
3. If no meaning found, generate default text

### Narrative Generation

- **Single Card**: Emphasizes yes/no answer
- **3 Card**: Creates timeline story (past → present → future)
- **Multi-Card**: Combines positions into cohesive narrative
- **Love/Career**: Relationship-specific or work-specific language

## Usage Examples

### JavaScript
```javascript
// Draw and create reading
fetch('/wp-json/tarot/v1/reading', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        question: "What should I focus on?",
        spread_type: "3card"
    })
})
.then(response => response.json())
.then(reading => {
    console.log(reading.interpretation.interpretation);
    // Shows: "Your reading begins with..."
});
```

### PHP (within WordPress)
```php
require_once TAROT_PATH . 'includes/interpreter.php';

$cards = []; // Array of card data with meanings
$interpretation = TarotInterpreter::interpret($cards, '3card', 'My question?');

echo $interpretation['interpretation'];
```

## Adding New Spreads

1. Add spread to `$default_spreads` in `tarot_api_get_spread()`
2. Add positions to `get_default_positions()`
3. Create `interpret_[spread_name]()` method in `TarotInterpreter`
4. Method should return array with:
   - `title`: Display title
   - `summary`: Question display
   - `interpretation`: Narrative text
   - `cards_display`: Array of individual cards
   - `answer`: (optional) For yes/no spreads

### Example
```php
private function interpret_custom_spread($cards, $question) {
    $positions = ['Position 1', 'Position 2', ...];
    $cards_display = [];
    
    foreach ($cards as $index => $card) {
        $cards_display[] = [
            'position' => $positions[$index],
            'card_name' => $card['card']['name'],
            'orientation' => $card['is_reversed'] ? 'Reversed' : 'Upright',
            'meaning' => $this->get_meaning($card)
        ];
    }
    
    return [
        'title' => 'Custom Spread',
        'summary' => "Your question: \"$question\"",
        'interpretation' => "Narrative here...",
        'cards_display' => $cards_display
    ];
}
```

## Frontend Display

### Main Interpretation
- Shows synthesized narrative first
- Yes/No answer prominently for single card
- Encourages reading as a whole

### Individual Cards (Collapsible)
- Click "📖 Click to see individual cards" to expand
- Shows each card with position, orientation, truncated meaning
- Allows deep dive into specific cards

## Key Features

✅ **Rule-based** - No AI required for initial implementation  
✅ **Contextual** - Different meaning depending on spread type  
✅ **Narrative** - Combines multiple cards into coherent story  
✅ **Reversible** - Upright/reversed meanings properly handled  
✅ **Spread-aware** - Yes/no for single, timeline for 3-card, etc.  
✅ **Scalable** - Easy to add new spreads  
✅ **Database-driven** - Uses stored meanings for synthesis  

## Future Enhancements

- AI-powered interpretation using OpenAI API
- User-defined custom interpretations
- Learning from user feedback
- Spread history and comparisons
- Tarot traditions (Rider-Waite, Thoth, etc.)
