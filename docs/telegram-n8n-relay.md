# Telegram alerts via n8n (jab hosting Telegram block kare, jaise InfinityFree)

Plugin -> (aapka n8n webhook) -> Telegram. Plugin ko Telegram se seedha baat nahi karni parti.

## n8n workflow (2 nodes)

1. **Webhook** node
   - HTTP Method: `POST`
   - Path: `aiap-telegram`
   - Respond: `Immediately`
2. **Telegram** node -> Operation: *Send Message*
   - Credential: apna Bot (BotFather wala token n8n credentials mein)
   - Chat ID (expression): `{{ $json.body.chat_id }}`
   - Text (expression): `{{ $json.body.text }}`
   - Additional Fields -> Parse Mode: `HTML`
   - Additional Fields -> Append n8n Attribution: off

Workflow ko **Active** karo, phir Webhook node ka **Production URL** copy karo
(`.../webhook/aiap-telegram`, na ke `webhook-test`).

## Plugin settings

Settings -> Notifications -> Telegram:
- Chat/User ID: apni numeric ID
- Webhook relay URL: wahi Production URL
- Bot Token khali chhor sakte ho (token n8n ke paas hai)
- "Telegram test bhejo" dabao.

Plugin har chat ID ke liye alag request bhejta hai: `chat_id`, `text` (HTML), `text_plain`, `site`.
