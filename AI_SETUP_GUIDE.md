# AI Content Assistant Setup Guide

## Overview

The XQUANTORIA AI Content Assistant provides comprehensive AI-powered features for content creation, optimization, and analysis using OpenAI's GPT-4 and DeepL translation services.

## Features

### 1. Content Generation
- **Full Article Generator**: Create complete, structured articles with custom outlines
- **Content Ideas Generator**: Brainstorm content topics and angles
- **Headline Suggestions**: Generate compelling, click-worthy headlines
- **Customizable Parameters**: Tone, audience, word count, creativity level

### 2. SEO Optimization
- **SEO Analysis**: Comprehensive SEO scoring with actionable recommendations
- **Meta Description Generator**: Create optimized meta descriptions
- **Auto-Tagging**: Generate relevant tags based on content analysis
- **Keyword Generation**: Suggest SEO-friendly keywords

### 3. Content Analysis
- **Sentiment Analysis**: Analyze emotional tone and sentiment
- **Plagiarism Check**: Semantic similarity detection against existing content
- **Proofreading**: Grammar, style, and clarity improvements
- **Readability Scoring**: Assess content readability

### 4. Translation
- **DeepL Integration**: Professional-quality translations
- **Auto Language Detection**: Automatically detect source language
- **Multi-Language Support**: EN, DE, FR, ES, IT, NL, PL and more

### 5. Image Generation
- **DALL-E 3 Integration**: Generate custom images from text descriptions
- **Multiple Sizes**: Square, landscape, and portrait formats
- **Style Options**: Vivid (hyper-realistic) or Natural (subtle)

### 6. AI Chatbot
- **Interactive Chat**: Real-time AI conversations
- **RAG Support**: Knowledge base integration for accurate responses
- **Context Awareness**: Maintains conversation history

### 7. Recommendation Engine
- **Related Posts**: ML-based content similarity matching
- **Personalized Feed**: User behavior-driven recommendations
- **Collaborative Filtering**: "Users who liked this also liked..."
- **Trending Content**: Popular posts based on engagement

## Installation

### Backend Setup

1. **Install Dependencies**:
```bash
cd backend
composer install
```

2. **Environment Configuration**:

Add to your `.env` file:

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-api-key-here
OPENAI_API_URL=https://api.openai.com/v1
OPENAI_MODEL=gpt-4
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_MAX_TOKENS=4000
OPENAI_TEMPERATURE=0.7

# DeepL Configuration
DEEPL_API_KEY=your-deepl-api-key-here
DEEPL_API_URL=https://api-free.deepl.com/v2/translate
```

3. **Run Migrations**:
```bash
php artisan migrate
```

4. **Clear Cache**:
```bash
php artisan config:clear
php artisan cache:clear
```

### Frontend Setup

1. **Install Dependencies** (if not already installed):
```bash
cd frontend
npm install
```

2. **Environment Configuration**:

Add to `.env`:
```env
VITE_API_URL=http://localhost:8000/api/v1
```

3. **Start Development Server**:
```bash
npm run dev
```

## API Endpoints

All AI endpoints require authentication and appropriate permissions (Author role and above).

### Content Generation
- `POST /api/v1/ai/generate-full-article` - Generate complete articles
- `POST /api/v1/ai/generate-content` - Generate content snippets
- `POST /api/v1/ai/generate-ideas` - Brainstorm content ideas
- `POST /api/v1/ai/suggest-headlines` - Get headline suggestions

### SEO & Analysis
- `POST /api/v1/ai/optimize-seo` - Analyze and optimize for SEO
- `POST /api/v1/ai/generate-tags` - Auto-generate tags
- `POST /api/v1/ai/generate-keywords` - Generate SEO keywords
- `POST /api/v1/ai/check-plagiarism` - Check content uniqueness
- `POST /api/v1/ai/analyze-sentiment` - Analyze sentiment
- `POST /api/v1/ai/proofread` - Proofread and improve text

### Translation
- `POST /api/v1/ai/translate-content` - Translate content using DeepL

### Image Generation
- `POST /api/v1/ai/generate-image` - Generate images with DALL-E 3

### Chat & RAG
- `POST /api/v1/ai/chat` - Interactive AI chat
- `POST /api/v1/ai/rag-chat` - RAG-based chat with knowledge base

### Utility
- `GET /api/v1/ai/check-availability` - Check if AI services are configured

## Usage Examples

### Generating a Full Article

```typescript
import { aiService } from './services/api';

const article = await aiService.generateFullArticle({
  topic: 'The Future of Web Development',
  tone: 'professional',
  target_audience: 'web developers',
  keywords: ['web dev', 'technology', 'future'],
  word_count: 2000,
  temperature: 0.7
});
```

### SEO Optimization

```typescript
const analysis = await aiService.optimizeSEO(
  '10 Tips for Better SEO',
  postContent
);
```

### Translation

```typescript
const translated = await aiService.translateContent(
  'Hello, world!',
  'DE', // German
  'EN'  // English (auto-detect if omitted)
);
```

### Image Generation

```typescript
const image = await aiService.generateImage(
  'A futuristic city with flying cars at sunset',
  '1792x1024',
  'vivid'
);
```

### AI Chat

```typescript
const response = await aiService.chat([
  { role: 'system', content: 'You are a helpful assistant.' },
  { role: 'user', content: 'What is SEO?' }
]);
```

## Prompt Engineering

The AI Content Assistant uses optimized prompts for each use case:

### Content Generation Prompts
- System role: Expert content writer
- Focus: SEO-optimized, engaging content
- Structure: Introduction → Body → Conclusion
- Tone: Configurable (professional, casual, technical, etc.)

### SEO Analysis Prompts
- System role: SEO expert
- Output: Detailed scoring and recommendations
- Metrics: Keyword density, readability, meta suggestions

### Translation Prompts
- Service: DeepL API
- Quality: Professional human-level translation
- Features: Auto-detection, format preservation

### Image Generation
- Model: DALL-E 3
- Quality: Standard or HD
- Styles: Vivid or Natural

## Best Practices

1. **API Key Security**: Never commit API keys to version control
2. **Rate Limiting**: Be mindful of API rate limits
3. **Cost Management**: Monitor token usage and costs
4. **Content Review**: Always review AI-generated content before publishing
5. **Temperature Settings**:
   - 0.0-0.3: Factual, focused content
   - 0.4-0.7: Balanced creativity
   - 0.8-2.0: Highly creative, varied output

## Configuration Options

### OpenAI Models
- `gpt-4`: Most capable, best for complex tasks
- `gpt-4-turbo`: Faster, more cost-effective
- `gpt-3.5-turbo`: Fastest, most economical

### Temperature Settings
- Lower (0.0-0.3): More focused, deterministic
- Medium (0.4-0.7): Balanced creativity
- Higher (0.8-2.0): More creative, varied

### Image Sizes
- `1024x1024`: Standard square
- `1792x1024`: Landscape
- `1024x1792`: Portrait

## Troubleshooting

### Common Issues

1. **"OpenAI API key not configured"**
   - Check that OPENAI_API_KEY is set in `.env`
   - Run `php artisan config:clear`

2. **"DeepL API key not configured"**
   - Check that DEEPL_API_KEY is set in `.env`
   - Verify your DeepL API key is valid

3. **Rate Limit Errors**
   - Implement request queuing
   - Add exponential backoff
   - Consider upgrading API tier

4. **Slow Response Times**
   - Check API status
   - Optimize prompt length
   - Use appropriate model (GPT-3.5 for simple tasks)

## Cost Estimation

### OpenAI API (GPT-4)
- Input: $0.03 per 1K tokens
- Output: $0.06 per 1K tokens
- Estimated article (1500 words): ~$0.50-$1.00

### DeepL API
- Free tier: 500,000 characters/month
- Paid tier: Starts at €4.99/month

### DALL-E 3
- Standard: $0.040 per image
- HD: $0.080 per image

## Support

For issues or questions:
- Documentation: Check inline code comments
- API Status: https://status.openai.com
- OpenAI Docs: https://platform.openai.com/docs
- DeepL Docs: https://www.deepl.com/docs-api

## License

This implementation follows the XQUANTORIA CMS license terms.
