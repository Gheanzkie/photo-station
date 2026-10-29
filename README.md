# SCAN2FETCH - Photo Station

Standalone PHP photo capture tool used to take the student and parent pictures
that Scan2Fetch then reads by filename (`uploads/students/<student_no>.jpg`,
`uploads/parents/<parent_no>.jpg`). No database, no framework - plain PHP.

## Login

The station is protected by a single 4-digit PIN:

| Field | Value |
|-------|-------|
| PIN | `1234` |

Change it in `config.php` -> `'pin' => '1234'`. The PIN is stored in plain text in
that file, so keep the file outside version control if the repo is public
(`config.local.php` is already ignored).

## Screens

- **Sign in** - PIN entry (`index.php`), tab title `SCAN2FETCH Photo Station | Sign In`
- **Capture** - live camera, save / delete / recent strip
  (`SCAN2FETCH Photo Station | Student Pictures`)
- **Settings** - switch role (students / parents), folder and size options (`settings.php`)

## Files

```
index.php        UI: login, camera capture, recent thumbnails, delete
save.php         receives the JPEG, validates size, writes the file
delete.php       removes a picture
settings.php     role / folder / quality settings, writes config.local.php
lib.php          helpers: config load, folder map, listing, safe filenames
config.php       defaults: pin, base_dir, folders, labels, sizes, limits
config.local.php written at runtime by the settings UI (git-ignored)
Pictures/        the photos themselves (git-ignored - real people's data)
```

## Configuration (`config.php`)

| Key | Value | Meaning |
|-----|-------|---------|
| `pin` | `1234` | access PIN |
| `base_dir` | `Pictures` | root photo folder |
| `recent_limit` | `12` | thumbnails shown in the recent strip |
| `max_bytes` | `4 * 1024 * 1024` | max upload size per photo (4 MB) |
| `size` | see file | stored image dimension |
| `jpeg_quality` | see file | JPEG quality on save |

## Photo naming

Photos are matched by filename, not by database id:

```
Pictures/students/1001.jpg     <- student_no 1001
Pictures/parents/P-1001.jpg    <- parent_no P-1001
```
Copy them into Scan2Fetch under `public/uploads/students/` and
`public/uploads/parents/` using the same names and the app picks them up
automatically (`student_photo_url()` / `parent_photo_url()`).

## Run

Serve the folder with any PHP 7.4+ runtime, e.g.

```bash
php -S 0.0.0.0:8090
```
then open http://localhost:8090/ and enter the PIN.

> Camera access requires a secure context: `http://localhost` or HTTPS.
> On a LAN IP over plain HTTP the browser will block `getUserMedia`.

## Privacy

`Pictures/` is excluded from this repository. Only `.gitkeep` is tracked so the
folder exists after cloning. Never commit real student or parent photos.
