# Calendar module guidelines

## Controllers

For controllers in `src/Calendar/Transport/Controller`:

- Do not use `private readonly` constructor property promotion.
- Use the team convention with `public readonly` promoted dependencies (or inject services without promoted properties when delegating to a dedicated service is more appropriate).
- Keep controllers thin and orchestration-only.
- Do not add `private` methods in controllers. Move reusable/complex logic to an application/domain service.
