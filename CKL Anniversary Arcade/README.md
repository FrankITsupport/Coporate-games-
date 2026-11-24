# CKL Anniversary Arcade

Three responsive, browser-based games built from the supplied CKL 120-year campaign content.

Open `dist/index.html`, or serve `dist/` with any static HTTP server. No installation or build is required. Optional Google Fonts have local serif/sans-serif fallbacks.

- Grow Together: five timed questions, each correct answer adds to an illustrated farm.
- The CKL Time Machine: six milestone dates and six story questions.
- The Heritage Wheel: five spins selecting from eight categories, with no repeated question within a game.
- All games score out of 1,000. Correct answers earn equal points; speed is not a tie-breaker.
- Sound defaults off. Reduced-motion preference is respected.
- High scores, optional first names, and the tree-location pledge stay in the current browser's local storage. There is no shared event backend or prize inventory.
- The green pledge does not confirm a real tree planting.

Content: `dist/content.js` contains 46 quiz questions and six milestones, transcribed from the supplied PDF. The PDF's two additional participation prompts are implemented as the pledge and planting-location vote. Historical answers follow that supplied campaign document. Some dates differ from CKL's public heritage page (for example, the local company chapter); reconcile with the campaign owner before live event use.

Artwork: supplied anniversary JPEG in `dist/assets/anniversary.jpeg`, viewable through Campaign artwork. The farm and wooden wheel are original inline SVG/CSS illustrations. CKL header is typographic.

QA: `qa.cjs` records full browser playthroughs, scoring, local persistence, timeout handling, exit/resume, HTML escaping, and mobile layout checks. Its Playwright and Chromium paths refer to the existing local environment. Run with `node qa.cjs` while serving `dist/` on port 4173.

Answer feedback opens a focused educational dialog after correct answers, incorrect answers, and timeouts. The dialog includes the correct answer and the supplied historical explanation; Continue advances play. Reading time is untimed.

At viewport widths of 651px and above, the header, home, instructions, games and results share a fixed 100dvh layout. Phone layouts retain normal document scrolling. `qa-viewport.cjs` verifies layouts at 1024x600, common laptop and tablet sizes, 1920x1080 and 3840x2160, and checks the modal continuation behavior.
