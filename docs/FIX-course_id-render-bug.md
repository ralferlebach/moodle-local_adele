# Fix: Student-Runtime-Ansicht rendert keine Knoten (course_id-Filter-Bug)

**Session 003, Teil 22 — local_adele 0.4.14 (2026072407)**

## Symptom

Sieben `@javascript`-Behat-Szenarien der Student-Runtime-Ansicht scheitern
mit Timeout auf `[data-id='dndnode_2']` — der Knoten (und tatsächlich der
gesamte Lernpfad) rendert nicht. Editor-/Admin-Szenarien laufen grün.

## Ursachenanalyse (gegen die tatsächliche Codebase, inkl. Vue3-Quelle)

1. Vue3 `StudentView.vue` rendert den Pfad aus `fetchUserPathRelation`
   (→ `get_lp_user_path_relation.php` → `learning_paths::get_learning_user_relation()`).
   Ist die Antwort der „not found"-Fallback (`lp_deleted = true`), wird nur
   ein Hinweis gezeigt und **kein einziger Knoten** — genau das Timeout-Bild.
2. `UserPath.vue` setzt `nodes = user_learningpath.json.tree.nodes` **ohne
   jeden Filter**; VueFlow bildet `data-id` 1:1 aus `node.id`. Fehlt ein
   Knoten im DOM, fehlt er also in der Backend-Antwort, nicht im Frontend.
3. `get_learning_user_relation()` filtert per SQL auf
   `AND lpu.course_id = :courseid` (Kontext-/Host-Kurs).
4. Der Subscribe (`enrollment::subscribe_user_to_learning_path()`) speichert
   den Snapshot mit `course_id = ($courseid ?? 0)` — dem Kurs des **ersten**
   auslösenden Ereignisses — und aktualisiert ihn danach nie (der
   Unique-Index `useridlpid (user_id, learning_path_id)` lässt spätere
   Subscribes die vorhandene Zeile wiederverwenden).
5. Der Index-Kommentar in `db/install.xml` sagt selbst: **„independent of
   the host course"** — es gibt garantiert genau **einen** Snapshot pro
   (Nutzer, Pfad). Der `course_id`-Filter im Abruf ist damit nicht nur
   überflüssig, sondern schädlich: Weicht der gespeicherte (beliebige)
   `course_id` vom Betrachtungs-Kurskontext ab, wird der einzige gültige
   Snapshot nicht gefunden → „not found" → kein Rendering.

Das ist ein halb abgeschlossener Refactor: `buildsqlqueryuserpath()` wurde
von `course_id` entkoppelt (Spec 2.1), die Abruf-Funktionen nicht.

## Fix

`course_id` aus dem `WHERE` von **drei** Abruf-Funktionen entfernt (Schlüssel
jetzt exakt der Unique-Index):
- `learning_paths::get_learning_user_relation()` (Student-Ansicht)
- `learning_paths::get_learning_user_relations()` (Lehrkraft-Ansicht — sonst
  fallen Lernende mit abweichendem gespeichertem `course_id` aus der Liste)
- `helper\user_path_relation::get_user_path_relation()` (Fallback des
  `update_user_path`-Adhoc-Tasks, der gated nodes freischaltet, + Speichern)

**Bewusst unangetastet:** `get_active_user_path_relation($userid, $courseid)`
— fragt „welche Pfade hat der Nutzer in *diesem* Kurs aktiv" und triggert
Recomputes; hier ist `course_id` inhaltlich gewollt und liegt nicht im
Render-Pfad.

## Verifikation

`php -l` sauber. Nicht gegen echte Instanz/CI bestätigt — beruht auf der
Analyse der tatsächlichen Codebase (Vue3 + PHP) und dem dokumentierten
Unique-Index, nicht auf einem eigenen Testlauf.

## Randnotiz

Im Repo liegen 444 `*Zone.Identifier`-Dateien (Windows-Artefakte). Harmlos
für die Tests (matchen kein `*.php`-Glob), aber sie gehören nicht ins Git.
Bereinigung z. B. mit:
`find . -name '*Zone.Identifier' -delete` und einem `.gitignore`-Eintrag.
