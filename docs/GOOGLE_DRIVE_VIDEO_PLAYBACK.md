# Secure Google Drive Video Playback

This document describes setup and operation for the private Google Drive video system in T-Square LMS.

## Google Cloud setup

1. Create a Google Cloud project.
2. Enable **Google Drive API**.
3. Configure OAuth consent screen (External or Internal as appropriate).
4. Create OAuth Client ID (Web application).
5. Add authorized redirect URI:
   - Production: `https://api.tsquarecenter.com/api/admin/google-storage-accounts/callback`
   - Local example: `http://t-square-lms.test/api/admin/google-storage-accounts/callback`
6. Required scopes:
   - `https://www.googleapis.com/auth/drive.readonly`
   - `openid`
   - `email`

## Environment variables (backend only)

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
```

Do **not** store refresh tokens in `.env`. Tokens are stored per account in `google_storage_accounts` using Laravel encrypted casts.

## Admin workflow

1. Open **Admin → Google Storage Accounts**
2. Click **Add Account**
3. Click **Connect** and complete Google OAuth consent
4. Upload video manually to **private** Google Drive (owned by or shared with the connected account)
5. Copy the Google Drive file URL
6. Open **Admin → Courses → Edit Course**
7. Assign the Google Storage Account in **Settings**
8. Open the **Lessons** tab
9. Add lesson, choose **Google Drive**, paste URL, save lesson

## Student workflow

1. Login
2. Enroll in course (completed payment if paid)
3. Open enrolled course
4. Open a lesson with video
5. Watch inside the platform player (no Google Drive UI exposed)

## Multiple accounts

- Each account has its own encrypted OAuth tokens in the database
- Assign one account per course via `google_storage_account_id`
- Use **Reconnect** if tokens expire/revoke
- Use **Test Connection** to verify Drive access
- Use **Disconnect** to revoke platform-side credentials

## Nginx recommendations (aaPanel)

For long video streaming through Laravel proxy, verify these settings on the API site:

```nginx
proxy_buffering off;
proxy_request_buffering off;
proxy_read_timeout 3600s;
send_timeout 3600s;
client_max_body_size 20m;
```

Apply only if playback tests show buffering/timeouts. Do not change unrelated locations.

## Known limitations

- This is **access control**, not DRM
- Determined users can still inspect network traffic or screen-record
- Watermark and disabled download controls are deterrents only
- Large concurrent streaming through PHP proxy may need CDN/offloading later

## Manual verification checklist

- [ ] OAuth connect/disconnect works
- [ ] Lesson save validates Drive URL and stores file ID only
- [ ] Enrolled student can authorize playback
- [ ] Non-enrolled student gets 403
- [ ] Stream endpoint supports seek/range
- [ ] API responses never expose Drive URLs or Google tokens
