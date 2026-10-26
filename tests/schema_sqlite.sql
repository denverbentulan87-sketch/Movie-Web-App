-- SQLite mirror of the MariaDB schema (movies_db.sql + migrations/001) for the test harness only.
PRAGMA foreign_keys = ON;
CREATE TABLE users (
  user_id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE movie_watchlist (
  watchlist_id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
  movie_title TEXT NOT NULL,
  genre TEXT,
  status TEXT DEFAULT 'Plan to Watch' CHECK (status IN ('Watching','Watched','Plan to Watch')),
  rating INTEGER DEFAULT 0 CHECK (rating BETWEEN 0 AND 5),
  description TEXT,
  cover_url TEXT,
  date_added TEXT DEFAULT CURRENT_TIMESTAMP,
  is_favorite INTEGER NOT NULL DEFAULT 0,
  priority TEXT NOT NULL DEFAULT 'Normal' CHECK (priority IN ('Low','Normal','High')),
  watch_count INTEGER NOT NULL DEFAULT 0,
  last_watched_at TEXT,
  personal_notes TEXT,
  notes_spoiler INTEGER NOT NULL DEFAULT 0,
  external_movie_id TEXT, release_date TEXT, release_year INTEGER, runtime_minutes INTEGER,
  director TEXT, cast_members TEXT, language TEXT, country TEXT,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (watchlist_id, user_id)
);
CREATE TABLE tags (
  tag_id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
  name TEXT NOT NULL COLLATE NOCASE,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id, name), UNIQUE (tag_id, user_id)
);
CREATE TABLE movie_tags (
  movie_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (movie_id, tag_id),
  FOREIGN KEY (movie_id, user_id) REFERENCES movie_watchlist (watchlist_id, user_id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id, user_id) REFERENCES tags (tag_id, user_id) ON DELETE CASCADE
);
CREATE TABLE collections (
  collection_id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
  name TEXT NOT NULL COLLATE NOCASE, description TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id, name), UNIQUE (collection_id, user_id)
);
CREATE TABLE collection_movies (
  collection_id INTEGER NOT NULL, movie_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
  added_at TEXT DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (collection_id, movie_id),
  FOREIGN KEY (collection_id, user_id) REFERENCES collections (collection_id, user_id) ON DELETE CASCADE,
  FOREIGN KEY (movie_id, user_id) REFERENCES movie_watchlist (watchlist_id, user_id) ON DELETE CASCADE
);
CREATE TABLE watch_history (
  history_id INTEGER PRIMARY KEY AUTOINCREMENT,
  movie_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
  watched_at TEXT NOT NULL, rating INTEGER, notes TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (movie_id, user_id) REFERENCES movie_watchlist (watchlist_id, user_id) ON DELETE CASCADE
);
