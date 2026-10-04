LOCAL FRONTEND LIBRARIES
========================

The application links Bootstrap 5 and jQuery from THIS folder when the files
are present, and automatically falls back to the CDN when they are missing.
To run 100% offline (no internet in the exam hall), drop these three files in:

  public/assets/css/bootstrap.min.css
      -> https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css

  public/assets/js/bootstrap.bundle.min.js
      -> https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js

  public/assets/js/jquery.min.js
      -> https://code.jquery.com/jquery-3.7.1.min.js

XAMPP one-liner (PowerShell, run from cbt_system/public/assets):

  Invoke-WebRequest https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css -OutFile css/bootstrap.min.css
  Invoke-WebRequest https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js -OutFile js/bootstrap.bundle.min.js
  Invoke-WebRequest https://code.jquery.com/jquery-3.7.1.min.js -OutFile js/jquery.min.js

Already bundled here (project-owned files, no download needed):
  css/cbt.css   -> custom CBT styles (timer, options, palette, diagrams)
  js/cbt.js     -> jQuery/AJAX engine (autosave, palette, timer, auto-submit,
                   lightbox zoom, admin modal helpers)
