# Route Contracts: Personal Knowledge Base RAG System

**Branch**: `001-knowledge-base-rag` | **Date**: 2026-02-06

## Public Routes (no auth)

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/health` | HealthController@show | health | FR-085 |
| GET | `/login` | LoginController@showForm | login | FR-002 |
| POST | `/login` | LoginController@login | login.store | FR-002, FR-006 |
| POST | `/logout` | LoginController@logout | logout | FR-002 |

## Authenticated Routes (web + auth middleware)

### Auth / Profile

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/password/change` | PasswordChangeController@showForm | password.change | FR-001a |
| POST | `/password/change` | PasswordChangeController@update | password.change.store | FR-001a, FR-004 |
| GET | `/profile` | ProfileController@edit | profile.edit | FR-003 |
| PUT | `/profile` | ProfileController@update | profile.update | FR-003 |

### Chat (behind ForcePasswordChange middleware)

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/chat` | ChatController@index | chat.index | FR-034 |
| GET | `/chat/{conversation}` | ChatController@show | chat.show | FR-034, FR-045 |
| POST | `/chat/{conversation}/stream` | ChatController@stream | chat.stream | FR-034, FR-037 |
| POST | `/chat/search` | ChatController@search | chat.search | FR-038 |

### Conversations

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/conversations` | ConversationController@index | conversations.index | FR-046 |
| POST | `/conversations` | ConversationController@store | conversations.store | FR-045 |
| PUT | `/conversations/{conversation}` | ConversationController@update | conversations.update | FR-048, FR-049 |
| DELETE | `/conversations/{conversation}` | ConversationController@destroy | conversations.destroy | FR-050 |

### Recaps

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/recaps` | RecapController@index | recaps.index | FR-052, FR-053 |
| GET | `/recaps/{recap}` | RecapController@show | recaps.show | FR-053 |

### Notification Preferences

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/notifications/settings` | NotificationPreferenceController@edit | notifications.edit | FR-056, FR-057 |
| PUT | `/notifications/settings` | NotificationPreferenceController@update | notifications.update | FR-056, FR-057 |

## Admin Routes (web + auth + admin middleware)

### User Management

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/admin/users` | Admin\UserController@index | admin.users.index | FR-062 |
| GET | `/admin/users/create` | Admin\UserController@create | admin.users.create | FR-063 |
| POST | `/admin/users` | Admin\UserController@store | admin.users.store | FR-063, FR-001 |
| PUT | `/admin/users/{user}/role` | Admin\UserController@updateRole | admin.users.role | FR-064 |
| PUT | `/admin/users/{user}/status` | Admin\UserController@updateStatus | admin.users.status | FR-065 |
| DELETE | `/admin/users/{user}` | Admin\UserController@destroy | admin.users.destroy | FR-066 |

### Source Management

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/admin/sources` | Admin\SourceController@index | admin.sources.index | FR-023 |
| GET | `/admin/sources/create` | Admin\SourceController@create | admin.sources.create | FR-017, FR-020, FR-021 |
| POST | `/admin/sources` | Admin\SourceController@store | admin.sources.store | FR-017, FR-020, FR-021 |
| GET | `/admin/sources/{source}/edit` | Admin\SourceController@edit | admin.sources.edit | FR-024 |
| PUT | `/admin/sources/{source}` | Admin\SourceController@update | admin.sources.update | FR-024 |
| DELETE | `/admin/sources/{source}` | Admin\SourceController@destroy | admin.sources.destroy | FR-025 |
| POST | `/admin/sources/{source}/reindex` | Admin\SourceController@reindex | admin.sources.reindex | FR-026 |
| POST | `/admin/sources/{source}/rechunk` | Admin\SourceController@rechunk | admin.sources.rechunk | FR-027 |
| POST | `/admin/sources/rechunk-all` | Admin\SourceController@rechunkAll | admin.sources.rechunk-all | FR-027 |
| POST | `/admin/sources/upload` | Admin\SourceController@upload | admin.sources.upload | FR-021, FR-022 |

### System Settings

| Method | URI | Controller | Name | FR |
|--------|-----|------------|------|-----|
| GET | `/admin/settings` | Admin\SettingsController@edit | admin.settings.edit | FR-068-FR-075 |
| PUT | `/admin/settings/branding` | Admin\SettingsController@updateBranding | admin.settings.branding | FR-068 |
| PUT | `/admin/settings/llm` | Admin\SettingsController@updateLlm | admin.settings.llm | FR-069 |
| PUT | `/admin/settings/embedding` | Admin\SettingsController@updateEmbedding | admin.settings.embedding | FR-070, FR-072 |
| POST | `/admin/settings/models/refresh` | Admin\SettingsController@refreshModels | admin.settings.models.refresh | FR-071 |
| PUT | `/admin/settings/chat` | Admin\SettingsController@updateChat | admin.settings.chat | FR-073, FR-074, FR-075 |
| PUT | `/admin/settings/recap` | Admin\SettingsController@updateRecap | admin.settings.recap | FR-054, FR-055 |
| PUT | `/admin/settings/email` | Admin\SettingsController@updateEmail | admin.settings.email | FR-058 |
| POST | `/admin/settings/email/test` | Admin\SettingsController@testEmail | admin.settings.email.test | FR-059 |

## Middleware Stack

- **web**: Session, CSRF, cookies (all routes)
- **auth**: Authentication required
- **admin**: `role === 'admin'` gate check
- **ForcePasswordChange**: Redirects to `/password/change` if `must_change_password === true`
- **throttle:login**: 5 per minute on login route (FR-006)
- **ContentSecurityPolicy**: CSP headers on all responses (FR-015)

## Response Formats

- All routes return HTML (Blade views) except:
  - `POST /chat/{conversation}/stream` → SSE stream (`text/event-stream`)
  - `POST /chat/search` → JSON (raw vector search results)
  - `GET /health` → JSON `{"status": "ok"}`
  - `POST /admin/settings/models/refresh` → JSON (model list)
