# Profile API - Postman Links

Use these endpoints in Postman after login and setting Bearer token.

## Base URL

- `http://127.0.0.1:8000/api`

## Endpoints

- **GET Profile**
  - Method: `GET`
  - URL: `http://127.0.0.1:8000/api/profile`
  - Auth: `Bearer Token`

- **PATCH Update Profile**
  - Method: `PATCH`
  - URL: `http://127.0.0.1:8000/api/profile`
  - Auth: `Bearer Token`
  - Body (raw JSON):

```json
{
  "name": "new username",
  "email": "newmail@example.com",
  "full_name": "Student Full Name",
  "gender": "male",
  "avatar": "avatars/student.png",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

## Quick Postman Setup

- Create environment variable: `base_url = http://127.0.0.1:8000/api`
- Use URL as `{{base_url}}/profile`
- Add header: `Accept: application/json`
- In Authorization tab, select `Bearer Token` and paste user token
