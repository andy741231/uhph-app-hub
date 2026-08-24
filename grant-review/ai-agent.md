# AI Agent Findings — Model Selection for RCMI Tickets Plugin

Date: 2026-08-11
Purpose: Benchmark research + cost-efficient model routing for building the `rcmi-tickets` WordPress plugin (see `ticket-plan.md`).

## Model tiers

Models are ranked in four tiers for phased development. Each phase of `ticket-plan.md` picks a tier based on task complexity.

### Tier S — Hardest 10% / multi-day autonomous
The nuclear option. Tasks where getting it wrong costs more than the tokens: architecture for the architecture, multi-day autonomous sessions, debugging loops where Tier 1 has already failed twice.

| Model | Price (in/out) | Why |
|---|---|---|
| Claude Fable 5 | $10/$50/M | SWE-bench Pro **80.3%** — 16+ points clear of every other model. State-of-the-art on CursorBench, FrontierBench, ViBench. Built for multi-day autonomous coding sessions. Use deliberately, at a chosen effort level, for the hardest tenth of your work. |

### Tier 1 — Lead / Heavy lifting
Architecture, security, complex agentic loops, cross-file debugging, build tooling.

| Model | Price (in/out) | Why |
|---|---|---|
| Claude Sonnet 5 | $2/$10/M | **Best value in Tier 1.** SWE-Pro 63.2% ≈ Sol (64.6%) at 40% of Sol's input price. "Most agentic Sonnet yet" — near-Opus coding, sustains focus on complex multi-file tasks. Daily driver for this project. |
| GPT-5.6 Sol | $5/M | Strongest Terminal-Bench (91.9%), DeepSWE 72.7. The safe pick when correctness is non-negotiable. |
| Kimi K3 | $3/M | Strong agentic (Terminal-Bench 88.3%, FrontierSWE 81.2). Fallback lead. |
| Claude Opus 4.7 | $5/$25/M | SWE-Verified 87.6% (best in class), MCP-Atlas 77.3% (best tool use). Upgrade path when Sonnet 5 or K3 hit a wall on agentic coherence. Expensive output ($25/M) — use sparingly. |

### Tier 2 — Heavy coding / cost-efficient primary
Substantial implementation work where specs are clear but complexity is real: SPA scaffolding, state management, REST endpoints, DB schema.

| Model | Price (input) | Why |
|---|---|---|
| DeepSeek V4 Pro | $0.435/M | Best coding bang-for-buck: SWE-Verified 80.6%, LiveCodeBench 93.5%, open weights. 11–29x cheaper than Sol. Weaker on very long agentic loops (Terminal-Bench 2.0 67.9%) — pair with a Tier 1 model for architecture/planning, then hand off implementation. |
| GPT-5.6 Terra | $2/M | Solid all-rounder (SWE-Pro 63.4%, Terminal-Bench 84.3%). Fallback when V4 Pro isn't available or for tasks needing stronger agentic coherence. |

### Tier 3 — Lightweight / mechanical
Well-specified, single-file or template work: email templates, simple components, updater boilerplate, one-shot generation from a detailed spec.

| Model | Price (input) | Why |
|---|---|---|
| GPT-5.6 Luna | $0.20/M | SWE-Pro 62.7% but weak on long autonomous tasks (4.7/10). Fine when the spec is airtight. |
| GLM 5.2 High | free | SWE-Pro 62.1%, FrontierSWE 74.4%. Good for mechanical multi-file work from spec. Weak on marathon tasks (SWE-Marathon 13.0). |

### Tier assignment rules
1. **Tier S is reserved.** Only escalate to Fable 5 when a Tier 1 model has failed twice on the same problem, or for multi-day autonomous sessions where the token cost of failure exceeds Fable 5's premium.
2. **Architecture, auth, permissions, debugging** → Tier 1 only. Never delegate to cheaper tiers.
3. **Feature implementation with clear spec** → Tier 2 primary, Tier 1 review pass.
4. **Boilerplate, templates, simple CRUD** → Tier 3. If the model loops or diverges, escalate to Tier 2.
5. **When in doubt, go up one tier.** Token cost of a wrong implementation dwarfs the price difference.

## Benchmark findings (web research, July–August 2026)

| Model | SWE-bench Verified | SWE-bench Pro | Terminal-Bench | Agentic / long-horizon |
|---|---|---|---|---|
| Claude Fable 5 | — | **80.3%** | ~77% (est.)† | State-of-the-art: CursorBench #1, FrontierBench #1, ViBench #1. Multi-day autonomous sessions. |
| Claude Opus 4.7 | 87.6% | 64.3% | 69.4% (2.0) | MCP-Atlas 77.3% (best tool use); 13% lift on 93-task coding benchmark over 4.6 |
| Claude Sonnet 5 | — | 63.2% | ~69% (est.)† | "Most agentic Sonnet yet"; near-Opus coding at 40% of Opus input price |
| GPT-5.6 Sol | — | 64.6% | 91.9% (2.1) | DeepSWE 72.7; strongest Terminal-Bench across board |
| Kimi K3 | 76.8%* | — | 88.3% (2.1) | DeepSWE 67.5, SWE-Marathon 42.0, FrontierSWE 81.2 |
| DeepSeek V4 Pro | 80.6%** | 55.4% | 67.9% (2.0) | LiveCodeBench 93.5%; weaker on very long agentic loops per third-party reviews |
| GPT-5.6 Terra | — | 63.4% | 84.3% (2.1) | BenchLM agentic 99th percentile |
| GLM 5.2 | — | 62.1% | 81.0% (2.1) | FrontierSWE 74.4; SWE-Marathon weak (13.0) |
| GPT-5.6 Luna | — | 62.7% | 82.5% (2.1) | Weak: long autonomous tasks 4.7/10 |

\* K3 reports SWE-bench *Verified* (contamination-prone per 2026 reporting), not Pro — not directly comparable.
\** V4 Pro SWE-bench Verified is vendor-reported (Hugging Face model card). NIST/CAISI independent eval scored it 74% (different scaffolding/token budget). SWE-bench Pro 55.4% is notably behind — V4 Pro excels at contained coding tasks, not multi-step agentic work.
† Sonnet 5 / Fable 5 Terminal-Bench estimated from Fable 5 − Sonnet 5 gap of +7.6 points reported by codingfleet.com (July 2026).

### Key insight
SWE-bench Pro is the differentiator. Most models cluster at 55–65% — but **Fable 5 breaks the
scale at 80.3%**, a 16-point gap that represents a genuine capability step change, not noise.
Below that, Sonnet 5, Opus 4.7, Sol, and Terra all sit within ~1.5 points of each other
(63–65%) — pick on price and agentic coherence, not the small delta. DeepSeek V4 Pro is an
outlier: elite at contained coding (SWE-Verified 80.6%, LiveCodeBench 93.5%) but middling on
agentic benchmarks (SWE-Pro 55.4%, Terminal-Bench 67.9%). Therefore:

- Mechanical, well-specified tasks → Tier 3 (Luna / GLM 5.2)
- Substantial implementation from clear specs → Tier 2 (V4 Pro / Terra)
- Architecture, security, debugging, daily driver → Tier 1 (Sonnet 5 / K3 / Sol)
- Tier 1 has failed twice, or multi-day autonomous → Tier S (Fable 5)

### Sources
- Claude family: anthropic.com/claude/sonnet, anthropic.com/claude/fable, anthropic.com/news/claude-opus-4-7, platform.claude.com/docs/pricing, codingfleet.com, packetnebula.com, benchr.org, verdent.ai
- Kimi K3: kimi.com/blog/kimi-k3, nxcode.io evaluation guide, siray.ai comparison
- GPT-5.6 family: developers.openai.com, benchlm.ai, llm-stats.com, codingfleet.com
- DeepSeek V4 Pro: huggingface.co/deepseek-ai/DeepSeek-V4-Pro, api-docs.deepseek.com, codersera.com review, NIST CAISI evaluation (nist.gov, May 2026)
- GLM 5.2: github.com/zai-org/GLM-5, emergent.sh/learn/glm-5-2-benchmark
- Caveat: most scores are vendor self-reported and harness-dependent. Treat as directional.

## Model assignment for this project

| Work type | Tier | Model | Rationale |
|---|---|---|---|
| Implementation plan, architecture | 1 | Claude Sonnet 5 | Near-Opus coding at $2/M. Daily Tier 1 driver. |
| SPA scaffold (Vite-in-WP, mount, routing, state) | 1→2 | Sonnet 5 plan, V4 Pro implement | Sonnet 5 designs architecture; V4 Pro generates scaffold at 4.6x lower input cost |
| Roles/capabilities, REST permission layer | 1 | Claude Sonnet 5 | Security-sensitive; Sonnet 5's agentic coherence handles permission edge cases |
| @mentions, reactions, comment threading UI | 1→2 | Sonnet 5 plan, V4 Pro implement | Fiddly state/edge cases — Sonnet 5 handles state design, V4 Pro codes it |
| DB schema, CRUD endpoints, filters, uploads | 2 | DeepSeek V4 Pro | Strong coding at $0.435/M; spec comes from Sonnet 5's plan |
| Email templates, simple components, updater copy | 3 | Luna / GLM 5.2 | One-shot generation from spec |
| Code review pass, integration debugging | 1 | Claude Sonnet 5 | Debugging is the most agentic task |
| Tier 1 has looped twice on same problem | S | Claude Fable 5 | Nuclear option — SWE-Pro 80.3%. Only when the token cost of being stuck exceeds Fable 5's premium. |

## Validation step (before committing)
Give V4 Pro, GLM 5.2, and Luna one identical, well-specified trial task (e.g. Task 6 from
`ticket-plan.md`: the comments REST endpoint). Compare output. Costs pennies, tells you more
than any leaderboard. Also test V4 Pro on a small agentic task (multi-file refactor) to
confirm the Terminal-Bench gap is real for your stack.

## Handoff discipline
The plan in `ticket-plan.md` labels every task with `[MODEL: ...]` and explicit STOP
conditions. Executing agents must follow them — the cost savings evaporate if a cheap model
burns tokens looping on a K3-tier problem.
