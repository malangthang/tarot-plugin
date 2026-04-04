# Tarot Pro Plugin - Advanced Features

## New Features Added

### 1. REST API Endpoints

#### Draw Single Card
```
GET /wp-json/tarot/v1/draw
```
Returns a random card with upright/reversed orientation and meanings.

#### Get Spread Information
```
GET /wp-json/tarot/v1/spread?type=3card
```
Available spread types: `1card`, `3card`, `celtic-cross`, `horseshoe`

#### Create Reading
```
POST /wp-json/tarot/v1/reading
Content-Type: application/json

{
    "question": "What should I focus on this week?",
    "spread_type": "3card"
}
```
Creates a complete reading with random cards (no duplicates), positions, and orientations.

#### Get Reading
```
GET /wp-json/tarot/v1/reading/123
```
Retrieves a saved reading by ID.

### 2. Frontend Tarot Reader

Use the new shortcode for a complete tarot reading experience:

```php
[tarot_reader]
```

#### Features:
- **Spread Selection**: Choose from 4 different spreads
- **Question Input**: Enter your question before reading
- **Shuffle Animation**: Visual card shuffling effect
- **Flip Cards**: Click cards to reveal full meanings
- **Reading Display**: Organized display of cards and positions
- **Save Readings**: Save readings for later reference

### 3. Reading Logic

#### Random Card Selection
- Cards are selected randomly without duplicates
- Each card can be upright or reversed (50/50 chance)

#### Position System
- **1 Card**: Focus
- **3 Card**: Past, Present, Future
- **Celtic Cross**: 10 positions for comprehensive reading
- **Horseshoe**: 7 positions for detailed guidance

#### Spread Types
- **Single Card**: Quick insights
- **Past-Present-Future**: Timeline reading
- **Celtic Cross**: Most comprehensive spread
- **Horseshoe**: Balanced guidance spread

### 4. Database Enhancements

New tables added:
- `tarot_spreads`: Predefined spread configurations
- `tarot_spread_positions`: Position definitions for each spread
- `tarot_readings`: Saved user readings with full data

### 5. Usage Examples

#### Basic Reading Page
Create a WordPress page with this content:
```php
[tarot_reader]
```

#### Custom Implementation
```javascript
// Draw a single card
fetch('/wp-json/tarot/v1/draw')
    .then(response => response.json())
    .then(data => {
        console.log('Card:', data.card.name);
        console.log('Reversed:', data.is_reversed);
    });

// Create a 3-card reading
fetch('/wp-json/tarot/v1/reading', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        question: 'What is my path?',
        spread_type: '3card'
    })
})
.then(response => response.json())
.then(reading => {
    console.log('Reading ID:', reading.reading_id);
    reading.cards.forEach(card => {
        console.log(card.position + ': ' + card.card.name);
    });
});
```

### 6. Styling

The tarot reader includes:
- Dark mystical theme with gold accents
- Smooth animations and transitions
- Responsive design for mobile devices
- Card flip effects
- Shuffle animations

### 7. Admin Integration

The admin interface now includes:
- Tabbed forms for better organization
- Media library integration for card images
- Enhanced card management with meanings by context

## Installation Notes

1. The plugin will automatically create new database tables on activation
2. Default spreads are created automatically
3. All existing functionality remains intact
4. New features are backward compatible

## API Response Examples

### Single Card Draw
```json
{
    "card": {
        "id": 1,
        "name": "The Fool",
        "arcana": "major",
        "suit": null,
        "image": "the-fool.jpg"
    },
    "meanings": {
        "upright": {
            "general": { "meaning": "New beginnings..." }
        },
        "reversed": {
            "general": { "meaning": "Recklessness..." }
        }
    },
    "is_reversed": false,
    "orientation": "upright"
}
```

### Reading Creation
```json
{
    "reading_id": 123,
    "spread": {
        "name": "Past, Present, Future",
        "total_cards": 3
    },
    "cards": [
        {
            "position": "Past",
            "card": { "name": "The Hermit" },
            "is_reversed": false,
            "orientation": "upright"
        }
    ],
    "question": "What should I focus on?"
}
```