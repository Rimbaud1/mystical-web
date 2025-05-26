# 🗺️ create\_map.md — Mystical Dungeons Map Builder

## 1. Purpose

`create_map.php` lets authenticated users design, validate, and store custom dungeon maps while enforcing gameplay constraints and theme coherence.

---

## 2. Files & Roles

| File                   | Role                                            |
| ---------------------- | ----------------------------------------------- |
| **create\_map.php**    | Full page: UI + PHP backend                     |
| `includes/navbar.php`  | Dynamic navigation bar                          |
| `algos/verif.exe`      | Returns `1/0` solvable ? (args: size, matrix)   |
| `algos/difficulte.exe` | Returns integer difficulty (args: matrix, size) |
| `includes/auth.php`    | Session helpers                                 |
| `includes/db.php`      | PDO connection                                  |
| `includes/helpers.php` | `flash()`, `redirect()`, etc.                   |

---

## 3. Database (table `Map`)

| column                    | type      | default               | note                       |
| ------------------------- | --------- | --------------------- | -------------------------- |
| id                        | INT AI PK |                       |                            |
| user\_id                  | INT FK    |                       | owner                      |
| name                      | VARCHAR   |                       |                            |
| creation\_date            | DATETIME  | NOW()                 |                            |
| map\_value                | TEXT      |                       | "row1;row2;..." digits 0‑6 |
| size                      | INT       |                       | NxN                        |
| map\_game\_count          | INT       | 0                     |                            |
| difficulty                | INT       | from `difficulte.exe` |                            |
| best\_player\_time        | INT       | 0                     |                            |
| best\_player\_moves       | INT       | 999                   |                            |
| best\_player\_time\_name  | VARCHAR   | "none"                |                            |
| best\_player\_moves\_name | VARCHAR   | "none"                |                            |

---

## 4. Tile Encoding

| Digit | Meaning       | CSS class      |
| ----- | ------------- | -------------- |
| 0     | Wall          | `.wall`        |
| 1     | Path          | `.path`        |
| 2     | Start         | `.start`       |
| 3     | Exit          | `.exit`        |
| 4     | Button        | `.button-tile` |
| 5     | Door (closed) | `.doorC`       |
| 6     | Door (open)   | `.doorO`       |

---

## 5. Client‑side Features

### 5.1 Grid Editor

* CSS `inline-grid` cells 30×30 px.
* Scrollable both axes (`grid-wrap`).
* Drag‑paint (`mousedown` → `painting`, `mouseover` paints) or hold **Space**.

### 5.2 JS‑Enforced Rules

1. Border cells only Wall/Start/Exit.
2. Start & Exit **forbidden in corners**.
3. No 2 × 2 block composed solely of Path / Button / Door.
4. Single Start & single Exit (new overrides old).

### 5.3 Loader

Grids > 40×40 rendered in batches with an overlay spinner.

---

## 6. Server‑side Validation

1. Basic size & character checks.
2. Corner rule duplicated.
3. **`verif.exe`** run for solvability; if `0`, flash error.
4. **`difficulte.exe`** computes difficulty.
5. Insert row in `Map`, initial stats (0, 999, "none").

> *Tip :* Wrap shell\_exec in a `run_cmd()` with timeout to avoid infinite hang if exe crashes.

---

## 7. CSS Textures

Each tile uses a one‑line inline‑SVG (data‑URI) – stone bricks, flagstones, glowing runes, wooden doors – matching the site’s medieval palette.

---

## 8. Flash Messages

`flash('error'| 'success', msg)`; rendered top‑page with Tailwind.

---

## 9. Extending with New Tile Types

1. Add digit + label to table above & `$tools` array.
2. Provide CSS `.newClass` with SVG.
3. Add digit to `nonWall` set if 2×2 rule applies.
4. (If pathable) adjust `matrixForVerif` when preprocessing.

---

## 10. Quick Workflow

1. Choose size, paint map (ensure Start & Exit).
2. Click **Save Dungeon**.
3. PHP validates, calls exes, writes DB.
4. Flash “Dungeon saved!” or error.

*Last update : 2025‑05‑21*
