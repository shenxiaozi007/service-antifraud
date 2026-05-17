# Draft: Multi-page Personal Blog

## Requirements (confirmed)
- User wants the email set to `173315279@qq.com`.
- User wants more second-level pages.
- Second-level page content can be sourced by summarizing the personal Obsidian knowledge base.
- Existing output target remains current directory's `opencode-html` unless changed.

## Technical Decisions
- Static HTML remains the likely delivery format because current artifact is a self-contained static page and user asked for generated HTML.
- Do not quote private Obsidian note contents verbatim; synthesize public-safe themes.
- Reuse the existing dark immersive visual language and optimize for smooth pointer interactions.
- Recommended structure: multiple standalone HTML files with shared `assets/css/site.css` and `assets/js/site.js`, because the current page is static and should remain easy to open locally.
- Test strategy default: tests-after via static parsing, link validation, inline/external JS syntax checks, and local file existence checks.
- Visual review recommends evolving into a "dark engineering atlas": premium, kinetic, multi-page, plain HTML/CSS/JS, with calmer article pages.

## Research Findings
- Current output has only `/Users/hxc/Documents/service-antifraud/opencode-html/index.html`; all CSS/JS are inline and reusable patterns include tokens, nav, cards, section scaffolding, reveal animation, canvas/pointer interactions.
- Current nav/contact placeholders: anchor links in `index.html`, archive rows point to `#contact`, contact CTA uses `mailto:your-email@example.com`.
- Safe public-facing themes from vault: engineering standards, Git workflow, backend layering, frontend standards, CI/CD quality gates, dev tools/productivity, remote access concepts, personal knowledge system.
- Representative safe sources include `wiki/index.md`, `wiki/overview/主题_研发规范与团队交付体系_综述.md`, `wiki/concepts/概念_后端分层架构_Controller_Business_DAO.md`, `README.md`, and `文档导航.md`.
- Sensitive content to avoid: weekly reports, manager briefs, recruitment/salary/interview banks, customer/company-specific CRM rules, infra details/IPs/ports/runbooks, cracked software guides, personal raw thoughts.
- Visual page inventory recommendation: `index.html`, `about.html`, `systems.html`, `writing.html`, `contact.html`, and `posts/*.html`.
- Visual performance guardrails: keep only ambient canvas, soft cursor glow, reveal-on-scroll; throttle pointer effects through RAF; reduce canvas particles on mobile; support `prefers-reduced-motion` and `visibilitychange` pause.

## Open Questions
- Confirm desired second-level page count/depth: curated 6-page set or fuller 8-page knowledge map.
- Confirm whether standalone `.html` pages are acceptable as default.
- Confirm whether to split shared CSS/JS now or keep duplicate inline code for speed.

## Scope Boundaries
- INCLUDE: Email replacement, navigation update, several second-level pages, summarized/anonymized knowledge content, local static verification.
- EXCLUDE: Publishing/deployment, backend/CMS, exposing private raw notes, copying lusion.co assets.
