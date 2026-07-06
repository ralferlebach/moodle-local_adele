# AdeLe — Manual Test Plan

A checklist of user stories for manually testing the **Learning Paths** plugins (the path **editor** and the
in-course **activity**). Work through it top to bottom. Each story is a small card: a one-line goal, a few
things to tick, and a Pass/Fail line. You don't need to know anything about the code — just an admin login.

---

## How to use this

- **Who it's for:** a tester with an **admin** account (so you can act as every role and configure the site).
- **What you need:** the admin login; a way to switch roles or use separate test accounts; and **command-line
  or cron access** for the few time-based stories (so scheduled background jobs run). If you can't run cron,
  those stories say how to trigger them from the admin Tasks screen instead.
- **Recording results:** for each card tick the checks you confirmed and mark **Pass** or **Fail**. On a fail,
  write what you saw in **Notes** (a screenshot helps).
- **Resetting between runs:** if something looks stale, go to *Site administration → Development → Purge caches*,
  and hard-refresh the browser (the path is a single-page app).
- **A note on labels:** exact wording depends on the site language (German/English) and version, and English
  labels may show a small leading `[number]` you can ignore. Match controls by their **position and purpose**
  described here, not the exact text.
- **Two sides of the app:**
  - **Editor** — *Site navigation → Learning Paths* (build and manage paths).
  - **Activity** — a *Learning path* activity added inside a course (what learners and their teacher use).

---

## ▶ Start here — 15-minute smoke pass

Do this first to get oriented and confirm the basics before the detailed list.

- [ ] **S1 — End-to-end happy path**
  As a new tester, I can build a path, publish it, and watch a learner progress.
  - [ ] In **Learning Paths**, create a path with **two course nodes** connected in order (node 2 after node 1)
  - [ ] On node 2 add a restriction "the previous node must be completed", and on node 1 a completion "course completed"
  - [ ] Save the path; add a **Learning path** activity to a course and link this path
  - [ ] Open the activity as a **student**: node 1 is available (▶), node 2 is locked (🔒)
  - [ ] Complete node 1's course → reopen the activity → node 2 is now available and you're enrolled in its course
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Epic A · Site configuration (admin)

_Settings live under **Site administration → Plugins → Local plugins → Learning path** (or search "Learning path" in admin)._

- [ ] **A1 — Course pool: subscribed-only vs all courses**
  As an admin, I control which courses editors can pick from.
  - [ ] Set the "Activate filter" option to **only subscribed courses** → in the editor's **Courses** panel, an
        editor sees only courses they teach/are in
  - [ ] Switch it to **all courses** → the panel lists all eligible courses
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **A2 — Filter the course pool by tag**
  As an admin, I can include/exclude courses by tag.
  - [ ] Tag a couple of courses; set **included tags** to that tag → only tagged courses appear in the editor panel
  - [ ] Move the tag to **excluded tags** → those courses disappear from the panel
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **A3 — Filter the course pool by category**
  As an admin, I can limit the pool to chosen categories.
  - [ ] Set the **category level** to one category → only that category's courses appear in the editor panel
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **A4 — Choose which restriction types editors may use**
  As an admin, I decide which access conditions are offered.
  - [ ] Remove a restriction type (e.g. the time-based one) from the "available restriction types" setting
  - [ ] In the editor, open a node's restriction editor → the removed type is no longer offered
  - [ ] Re-add it → it reappears
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **A5 — Quiz validation mode**
  As an admin, I set how quiz results count toward completion.
  - [ ] Change the "Quiz settings" option; build/open a node with a quiz completion condition
  - [ ] Confirm the quiz-completion behaviour matches the chosen mode (single attempt vs all attempts)
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **A6 — Role learners are enrolled as**
  As an admin, I choose the role used when a learner is enrolled into a path's course.
  - [ ] Set "Enrollment Settings" to a role (e.g. Student); have a learner reach/unlock a node
  - [ ] In that node's course → *Participants*, the learner has the chosen role
  - [ ] (Optional) set the **assistant** enrolment role and confirm an assistant gets that role
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Epic B · Access & roles

_Set up: a **Manager** (system role with the manage capability), an **Assistant** (system role with only the
assist capability), a **teacher** who owns a path, a **plain teacher**, and a **student**._

- [ ] **B1 — Manager can do everything**
  As a manager, I can manage all paths.
  - [ ] I see the **Learning Paths** area and **every** path
  - [ ] I can create, edit, duplicate, hide and delete any path
  - [ ] I can add and remove **editors** on a path
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **B2 — Assistant can edit any path but not manage editors**
  As an assistant, I help maintain paths.
  - [ ] I can open and edit any path
  - [ ] I **cannot** add/remove editors (that control is absent/disabled)
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **B3 — A path owner edits only their own paths**
  As a teacher who created a path, I manage just mine.
  - [ ] I see and can edit paths I created (or was added to as editor)
  - [ ] I do **not** see/edit paths that belong to other teachers
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **B4 — A student can only view, never edit**
  As a student, I just follow my path.
  - [ ] I have **no** access to the Learning Paths editor area
  - [ ] In a course activity I see my path read-only (no edit controls)
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **B5 — A learner's data stays private**
  As a learner, my progress and details aren't visible to other learners.
  - [ ] As student A, I can see my own progress
  - [ ] I have no way to view student B's path, progress, or email
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Epic C · Building a learning path (editor)

- [ ] **C1 — Create and name a path, set its image**
  As an editor, I start a new path with an identity.
  - [ ] Create a new path; set a title and description
  - [ ] Set its image using the **course image**, a **stock image**, and an **uploaded** image (try each)
  - [ ] Save; the path appears in the list with the chosen image
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C2 — Add the three node types**
  As an editor, I can place different kinds of nodes.
  - [ ] Add a **single-course node** (drag a course from the Courses panel)
  - [ ] Add a **stack / "Lernpaket"** node (a choice of several alternative courses)
  - [ ] Add a **learning-module node** (a non-course module)
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C3 — Connect nodes to set the order**
  As an editor, I define the sequence.
  - [ ] Draw a connection from one node to another
  - [ ] The connection persists after save and shows the intended order
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C4 — Restriction: fixed start/end date (timed)**
  As an editor, I can lock a node to a date window.
  - [ ] Open the node's restriction editor (lock icon) and add the **date window** condition
  - [ ] Set a start and end date/time; save
  - [ ] Reopen → the dates are stored as entered
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C5 — Restriction: relative duration (timed duration)**
  As an editor, I can open a node for a period after the learner starts.
  - [ ] Add the **duration** condition; choose **days / weeks / months** and a number
  - [ ] Choose the start reference: **since path subscription** vs **since node subscription**
  - [ ] Save and reopen → settings preserved
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C6 — Restriction: a specific node completed**
  As an editor, I can require one particular earlier node.
  - [ ] Add the **specific node completed** condition and pick a parent node; save/reopen → selection preserved
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C7 — Restriction: N of several parent nodes**
  As an editor, I can require "complete N of these".
  - [ ] Add the **parent nodes** condition; set the minimum count (N of M); save/reopen → count preserved
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C8 — Restriction: manual release**
  As an editor, I can require a teacher to release the node.
  - [ ] Add the **manual** restriction (checkbox) and optional info text; save/reopen → preserved
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C9 — Restriction: master override**
  As an editor, I can use the master restriction that overrides the rest.
  - [ ] Enable the **master restriction**; confirm it's presented as overriding other conditions
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C10 — Completion: course(s) completed**
  As an editor, I can complete a node when its course(s) are done.
  - [ ] Open the completion editor (checklist icon) and add **course completed**
  - [ ] For a multi-course/stack node, set **how many** courses are required; save/reopen → preserved
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C11 — Completion: quiz score**
  As an editor, I can complete a node on a quiz result.
  - [ ] Add **quiz** completion; pick a quiz in the node's course and set a minimum grade; save/reopen → preserved
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C12 — Completion: adaptive quiz (catquiz)**  *(skip if the adaptive-quiz/catquiz plugin isn't installed)*
  As an editor, I can complete a node on adaptive-quiz scales.
  - [ ] Add **catquiz** completion; pick the test and set scale/threshold values; save/reopen → preserved
  Result:  ⬜ Pass   ⬜ Fail   ⬜ N/A   Notes: ______________________________

- [ ] **C13 — Completion: manual & master**
  As an editor, I can complete a node by hand or via master override.
  - [ ] Add **manual** completion (checkbox + optional info)
  - [ ] Enable **master completion** and confirm it's presented as overriding other conditions
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C14 — Combine conditions with OR / AND**
  As an editor, I can require any-of or all-of.
  - [ ] Add two conditions joined by **OR**; the editor labels it OR
  - [ ] Add two joined by **AND**; the editor labels it AND
  - [ ] Build a **mixed** combination (e.g. one AND of two, with one of them an OR pair); save/reopen → preserved
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C15 — Node feedback text**
  As an editor, I can write feedback a learner sees on a node.
  - [ ] Add/edit feedback on a node; save/reopen → the text is preserved
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C16 — Duplicate, hide/show, delete a path**
  As an editor, I can manage a path's lifecycle.
  - [ ] **Duplicate** a path → a copy appears with the same structure
  - [ ] Toggle **visibility** (hide/show) → state changes and persists
  - [ ] **Delete** a path → it's confirmed and removed from the list
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C17 — Add a co-editor**
  As a manager, I can let another person edit a path.
  - [ ] On a saved path, search for a user and add them as an **editor**
  - [ ] That user can now open/edit the path; remove them and they can't
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **C18 — The editor stays stable on unusual paths**
  As an editor, the tool doesn't break on awkward structures.
  - [ ] Build a path where nodes connect back into a loop → the editor still loads and saves, no error message
  - [ ] Delete a node that another node depended on → the editor still loads; the dependent node isn't stuck with a broken reference
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Epic D · Publishing a path to a course (activity)

_Add a **Learning path** activity to a course (*Add an activity or resource → Learning path*)._

- [ ] **D1 — Link a path to the activity**
  As a teacher, I attach a learning path to my course.
  - [ ] Add the activity, choose a path in **Chosen Learning Path**, save → the activity opens that path
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **D2 — Choose the view (top vs floor level)**
  As a teacher, I pick how the path is shown.
  - [ ] Set **Choose view** to *top level* → learners see the whole path
  - [ ] Set it to *floor level* → confirm the alternative detail view
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **D3 — Results visibility (everyone vs own only)**
  As a teacher, I control who sees whose results.
  - [ ] Set **user list option** to *everyone sees all results* → the participant list shows all learners
  - [ ] Set it to *only own results* → a student sees just themselves
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **D4 — Subscription scope**
  As a teacher, I choose who gets subscribed to the path.
  - [ ] Set **how people get subscribed** to *everyone in this course* → enrolling a user in the course subscribes them
  - [ ] Set it to *subscribed to at least one starting node* → confirm that scoping
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **D5 — Activity completion when the path is finished**
  As a teacher, the activity can complete when the learner finishes the path.
  - [ ] Enable **Learningpath completion**; a learner who finishes the whole path gets the activity marked complete
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Epic E · Learner experience (the activity at runtime)

- [ ] **E1 — Locked vs available nodes**
  As a learner, I can see which nodes I can open.
  - [ ] A locked node shows a **🔒 lock**; an available one shows a **▶ play**
  - [ ] A time-based node shows the clock **"ring"** around the button
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **E2 — Unlock by completing a course**
  As a learner, finishing prerequisites opens the next step.
  - [ ] Complete a prerequisite course → the dependent node changes locked → available
  - [ ] I'm **automatically enrolled** into the newly available node's course
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **E3 — Unlock by passing a quiz**
  As a learner, a passing quiz score opens the next step.
  - [ ] Fail the quiz → the node stays locked
  - [ ] Pass it (meet the grade) → the node unlocks
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **E4 — A time-based node opens by itself**
  As a learner, a date/duration node opens at the right time without me revisiting.
  - [ ] Set a node's window to open shortly in the future; confirm it's locked
  - [ ] Let the time pass and run scheduled background jobs (`php admin/cli/cron.php` or
        `php admin/cli/adhoc_task.php --execute`; or *Site admin → Server → Tasks*) — **without** the learner reopening anything
  - [ ] Reopen as the learner → the node is now available (and the learner enrolled)
  - [ ] Change the window's date afterwards and re-run jobs → it opens at the new time, with no duplicate enrolment
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **E5 — Teacher manually unlocks/completes for me**
  As a learner, a teacher's manual action affects my node.
  - [ ] For a node with a manual condition, the teacher grants/completes it → my node opens/completes accordingly
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **E6 — Progress, rank, info & feedback**
  As a learner, I can see my standing and a node's details.
  - [ ] Each node shows a progress indicator; my overall **progress/rank** is shown
  - [ ] The node **info ("i")** popup shows what's required to open/complete it
  - [ ] The node **feedback (speech-bubble)** popup shows the teacher's feedback; opening it doesn't block clicks on other nodes
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **E7 — Names and text display correctly and safely**
  As a learner, course names/descriptions read correctly and never run as code.
  - [ ] Course names/summaries show the real names — even for courses I'm not enrolled in yet (not "Subcourse")
  - [ ] If a course or quiz name contains characters like `< >`, it shows as **plain text** (nothing pops up or executes)
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **E8 — Finishing the path**
  As a learner, completing everything finishes the path.
  - [ ] Complete all nodes → the path shows as finished; if enabled, the activity is marked complete
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **E9 — Empty or removed paths are handled gracefully**
  As a learner, edge cases show a friendly message, not an error.
  - [ ] Open an activity whose path has **no nodes** → a friendly empty state, no error
  - [ ] If a path I was on is **deleted**, opening it shows a "not found" message, not a crash
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Epic F · Teacher monitoring

- [ ] **F1 — Participant overview**
  As a teacher, I can see everyone's progress.
  - [ ] Open the activity as teacher → a participant list shows progress, completed-node count, and rank
  - [ ] Sorting by a column works
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **F2 — Inspect one learner**
  As a teacher, I can drill into a single learner.
  - [ ] Click a learner → their personal path opens showing their node statuses and progress
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Epic G · Language & onboarding

- [ ] **G1 — German and English**
  As a user, the app reads correctly in both languages.
  - [ ] Switch the site/user language to **German** → editor, activity, and feedback text are translated and read naturally
  - [ ] Switch to **English** → same check
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **G2 — In-app tutorial**
  As a new editor, the built-in guide works.
  - [ ] Open the **Introduction slider / tutorial**; step through **Part A** (build a path) and **Part B** (add to a course)
  - [ ] Steps, images, and captions display correctly
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Combination scenarios

A few realistic mixes to exercise conditions together (build each, then verify as a learner).

- [ ] **X1 — Date window + course completion**
  - [ ] A node restricted by a date window AND completed by course completion behaves correctly on both axes
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **X2 — Quiz OR manual completion**
  - [ ] A node that completes by **either** passing a quiz **or** a manual mark — either route completes it
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **X3 — Parent nodes AND date window**
  - [ ] A node requiring **N parent nodes complete** AND a date window stays locked until **both** are satisfied
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **X4 — Stack/"Lernpaket" with completion**
  - [ ] A stack node where the learner picks one of several courses completes when the required course(s) are done
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

- [ ] **X5 — Mixed logic: A AND (B OR C)**
  - [ ] Build a node gated by A AND (B OR C); verify it opens only for the correct combinations
  Result:  ⬜ Pass   ⬜ Fail     Notes: ______________________________

---

## Sign-off

| Field | Value |
|---|---|
| Tester | __________________________ |
| Date | __________________________ |
| Site / version tested | __________________________ |
| Language(s) tested | ⬜ German  ⬜ English |
| Cards passed / total | __________________________ |
| Overall result | ⬜ Pass  ⬜ Pass with issues  ⬜ Fail |
| Open issues / notes | __________________________________________ |
