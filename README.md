# Pennova AI – SEO Autoblogger

**Open-source WordPress plugin** that writes SEO-optimised articles with *any* AI provider, adds images and YouTube videos automatically, publishes on autopilot, and pings you on Telegram / WhatsApp.

> Status: **beta**. Works end-to-end in testing flows, but it has not been battle-tested on many hosts yet. Issues and PRs are very welcome.

![Pennova AI](assets/logo.svg)

> **100% free and open source.** No license keys, no paid tiers, no accounts. You only pay your own AI / API providers.

## Features

- **16 AI provider presets + custom endpoint**: OpenAI, Anthropic (Claude), Google Gemini, OpenRouter, Groq, DeepSeek, Mistral, xAI, Together, Perplexity, Fireworks, Cerebras, Cohere, Ollama, LM Studio, or any OpenAI-/Anthropic-compatible URL.
- **SEO**: meta title/description + focus keyword written to Yoast / Rank Math / AIOSEO, table of contents, key takeaways, FAQ + `FAQPage` schema, internal links from your own posts, outline-first mode.
- **Media**: Unsplash, Pexels or AI-generated images (OpenAI Images); relevant **YouTube videos** found by keyword (click-to-play, lazy, responsive). All images/tables/videos are mobile responsive.
- **Research (You.com)**: optional SERP insights (what ranks now) or cited web research with an auto-generated *Sources* list, plus clean Markdown extraction for source URLs.
- **Automation**: bulk keywords, scheduling gap, WP-Cron auto-pilot queue, keyword ideas, rewrite/improve existing posts.
- **Alerts**: Telegram bot (with automatic Chat-ID detection and an optional n8n relay) and WhatsApp Agent (Meta Agent Platform). Bulk runs send one summary.
- **Security**: API keys are stored encrypted (AES-256 with your site's salts); nonce + capability checks on every AJAX action.

## Install

1. Download the latest `pennova-ai-x.y.z.zip` from the repository's **Releases** page (or run `bash bin/build-zip.sh` to build it yourself).
2. WordPress → Plugins → Add New → Upload Plugin → Activate.
3. **Pennova AI → Settings**: choose a provider, paste the API key, press *Connection test*.
4. **Writer**: enter a keyword and press *Generate*. Start with `Draft` status.

## Providers

| Provider | Notes |
|---|---|
| OpenAI, Gemini, Groq, DeepSeek, Mistral, xAI, Together, Perplexity, Fireworks, Cerebras, Cohere | OpenAI-compatible `/chat/completions` |
| Anthropic | Native `/messages` |
| OpenRouter | One key, hundreds of models (reasoning models need higher *Max output tokens*) |
| Ollama / LM Studio | Local, no key (the server must be reachable from your WordPress host) |
| Custom | Any OpenAI- or Anthropic-compatible base URL |

Model names are plain text, so new models work without a plugin update. "Fetch models" loads the provider's live list.

## External services

The plugin only contacts services **you configure**: your AI provider, Unsplash / Pexels / OpenAI Images, the YouTube Data API, Telegram, and Meta's WhatsApp Agent API. Article text and keywords are sent to your chosen AI provider; alerts contain post titles and links. Nothing is sent to the plugin authors. See `readme.txt` for the full list and each service's terms.

## Known limitations

- UI strings are currently Roman Urdu / English mixed and not yet translatable (`__()` / `.pot` is on the roadmap).
- WhatsApp: Meta's agent can only message its creator, addressed as `user:<id>`, not by phone number.
- Free shared hosts may block Telegram or time out on long AI requests; use the n8n relay and shorter articles.
- AI output must be reviewed before publishing; the plugin tells the model not to invent facts, but cannot guarantee it.

## Roadmap

- [ ] Internationalisation (`__()`, `.pot`, English UI)
- [ ] Unit / integration tests (PHPUnit + wp-env)
- [ ] Gutenberg-block output option
- [ ] More image sources, more alert channels (Discord, Slack, email)
- [ ] Option to store only video IDs (no titles) for stricter YouTube API terms

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security issues: [SECURITY.md](SECURITY.md).

## License

GPL-2.0-or-later, see [LICENSE](LICENSE). Logo and icon (`assets/*.svg`) are released under the same license.
"Pennova" is a working name; check trademark availability before commercial use or distribution on plugin directories.
