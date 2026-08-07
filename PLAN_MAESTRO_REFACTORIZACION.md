# Plan Maestro de Refactorización — Leodega

> Documento de referencia. Diagnóstico realizado sobre el estado del repositorio al 2026-07-31.
> Objetivo: guiar la refactorización del sistema **sin alterar su comportamiento actual**, salvo en el punto de seguridad señalado explícitamente en la Fase 0.5.

Stack: **Laravel 12** (API REST, PHP 8.2) + **React 19 / TypeScript / Vite**. Monorepo `backend/` + `frontend/`. Proyecto académico (Ingeniería de Software II), en fase de integración/pruebas, sin CI/CD configurado. ~2.600 líneas PHP en `app/`, ~9.700 líneas TSX en `src/`.

---

## 1. Mapa de Arquitectura Actual

**Backend (Laravel 12):**

```
Route (routes/api.php) → Controller → Eloquent Model → DB
```

- No hay capa de Servicios real: solo existe `NotificationService`. No hay Repositories, no hay Laravel Policies, no hay FormRequests — toda la validación está inline en cada método de controlador.
- La mayoría de controladores (`UserController`, `LandlordsController`, `TenantsController`, `StorePhotoController`, etc.) **extienden `ApiController`**, una base "genérica" que hace CRUD por reflexión:
  ```php
  protected function storeModel(Request $request, string $modelClass, array $rules) { ... $modelClass::create($validated) ... }
  ```
  Esto es un anti-patrón de "Active Record + Smart Base Class": el HTTP layer queda acoplado directamente a Eloquent, sin lugar para reglas de negocio ni autorización por recurso.
- Unos pocos controladores (`ReservationsController`, `AuthController`) sí contienen lógica de negocio real (conflictos de fechas, tokens), pero directamente en el controlador — no hay dónde testear esa lógica de forma aislada.

**Frontend (React 19):**

```
App.tsx (router central) → Componente de página (fat) → axios directo (api.get/post) → backend
```

- No existe gestión de estado global (sin Context, Redux ni Zustand — confirmado por búsqueda exhaustiva en `src/`).
- No hay capa de servicios API: cada componente llama `api.get(...)`/`api.post(...)` inline.
- Autenticación/rol se resuelve leyendo `localStorage` **directamente en 15 archivos distintos** (incluyendo `Protected.tsx`, `Role.tsx` y componentes de página), en vez de un hook/contexto único.
- Estructura por tipo de archivo (`Components/`, `Dashboard/`, `Pages/`), no por dominio.

---

## 2. Matriz de Riesgo y Acoplamiento

| # | Hallazgo | Severidad | Tipo |
|---|----------|-----------|------|
| 1 | Rutas API sin middleware `auth:sanctum`: `/admin`, `/user` (GET), `/landlords`, `/tenants`, `/storePrices`, `/favorites`, `/storeDisponibility`, `/payments`, `/cancelations_polices`, `/store_moderation` accesibles sin autenticación, incluyendo CRUD completo sobre `Admin` | 🔴 Crítico (seguridad) | Config/rutas |
| 2 | `ApiController` (God base class): CRUD genérico por reflexión usado por ~10 controladores; sin autorización por recurso, sin separación validación/negocio | 🔴 Alto | Acoplamiento estructural |
| 3 | Inconsistencia archivo/clase: `storeRoomsController.php` contiene `class StoreRoomsController`; las rutas mezclan `storeRoomsController::class` y `StoreRoomsController::class`. Funciona en filesystems case-insensitive (Windows/Mac) pero es riesgo silencioso de autoload roto en Linux (producción vía Docker/Railway) | 🔴 Alto | Riesgo de despliegue |
| 4 | Modelo duplicado: `User.php` (real, tabla `user`, usado en toda la app) vs `Users.php` (huérfano, sin referencias). Migración `create_users_table` (tabla `users`, no usada) coexiste con `create_user_table` (tabla `user`, real) | 🟡 Medio | Código muerto / confusión |
| 5 | Sin Laravel Policies: autorización manual e inconsistente (ej. `ReservationsController::updateStatus` sí valida `landlord_id`, otros controladores CRUD genéricos no validan ownership en absoluto) | 🔴 Alto | Seguridad / negocio |
| 6 | Duplicación de lógica de auth/rol en frontend (15 archivos leen `localStorage` directamente) | 🟡 Medio | Duplicación |
| 7 | Componentes "God Component": `Settings.tsx` (614 líneas, 5 responsabilidades: perfil/notificaciones/seguridad/pagos/privacidad); `LeodegaUI.tsx` (435 líneas) | 🟡 Medio-Alto | Complejidad |
| 8 | `Mensajes.tsx` y `MensajesTendant.tsx` (317 líneas c/u) son casi el mismo componente duplicado para dos roles distintos | 🟡 Medio | Duplicación |
| 9 | Sin CI/CD (no hay GitHub Actions, solo `docker-compose.yml`) pese a tener instalado `phpstan`, `psalm`, `phpmd`, `pint`, `phpcpd` — herramientas de calidad presentes pero no automatizadas | 🔴 Alto | Sin red de seguridad |
| 10 | Cobertura de tests desigual: Feature tests cubren Reports/Messages/Notifications/Reservations/Ratings/StoreRoomModeration, pero no Auth, User, Payments, Landlords, Tenants, Favorites, Conversation. Frontend solo tiene 5 specs Cypress (e2e happy-path), sin tests unitarios (no hay Vitest/Jest) | 🔴 Alto | Sin red de seguridad |
| 11 | `firebaseConfig.js` inicializa Firebase Storage pero no se usa en ningún archivo (las fotos se guardan vía `Storage::store()` de Laravel, disco local) — integración muerta | 🟢 Bajo | Código muerto |

---

## 3. Plan de Acción por Fases (menor → mayor riesgo)

### Fase 0 — Red de seguridad
- CI (GitHub Actions): `phpunit`, `phpstan`/`psalm`, `pint --test`, `tsc --noEmit`, `eslint`, `cypress run`. Ningún PR se mergea sin este gate en verde.
- Characterization tests para los módulos sin cobertura hoy (hallazgo #10) — grabar el comportamiento *actual*, tal cual, antes de tocarlo.

### Fase 0.5 — Parche de seguridad: rutas sin autenticación ✅ Implementada
Se ejecutó después de tener CI en verde. Al implementarla contra el frontend real aparecieron dos desvíos respecto a la tabla original (documentados abajo) y un bug nuevo introducido por el propio parche, ya corregido.

**Clasificación ruta por ruta implementada** (`backend/routes/api.php`):

| Ruta | Estado anterior | Implementado | Razón |
|---|---|---|---|
| `/admin` (GET/POST/PUT/DELETE) | Sin auth | `auth.api:sanctum` + `role:admin` | CRUD completo de administradores expuesto públicamente |
| `/user` GET (index, show) | Sin auth | `auth.api:sanctum` (**sin** restringir a admin) | **Desvío del plan original**: `Mensajes.tsx`/`MensajesTendant.tsx` llaman `GET /user` para listar contactos de mensajería, no solo el panel admin. Restringir a `role:admin` habría roto la mensajería para landlord/tenant. |
| `/user` POST | Sin auth | **Se mantiene público** | **Desvío del plan original**: `Decision.tsx` usa este endpoint, sin sesión, como alta real de cuenta tras elegir rol. En vez de protegerlo, se añadió un guard en `UserController::store` que rechaza `role=admin` en peticiones no autenticadas (evita la auto-promoción a admin que existía antes). |
| `/user` PUT/DELETE | Sin auth | `auth.api:sanctum` + `role:admin` | Sin uso anónimo detectado en el frontend; autoedición pasa por `/profile` |
| `/tenants` CRUD | Sin auth | `auth.api:sanctum` | Datos de arrendatarios |
| `/landlords` GET | Sin auth | Público (sin cambio) | Se usa en fichas de bodega públicas |
| `/landlords` POST/PUT/DELETE | Sin auth | `auth.api:sanctum` | Alta/edición sin restricción hoy |
| `/storeRooms` GET (index/show/detail) | Sin auth | Público (sin cambio) | Catálogo de bodegas es la landing pública |
| `/storeRooms` POST/PUT/DELETE | Ruta duplicada (una con auth, otra sin) | Unificada bajo `auth.api:sanctum` | Se eliminó la definición duplicada insegura |
| `/store-rooms/{storeRoom}/photos` POST/DELETE | Sin auth | `auth.api:sanctum` | **No estaba en la tabla original** — se detectó al reescribir el archivo; mismo tipo de hueco (mutación sin autenticar) |
| `/storePrices`, `/storeDisponibility` GET | Sin auth | Público (sin cambio) | Parte de la ficha pública de la bodega |
| `/storePrices`, `/storeDisponibility` POST/PUT/DELETE | Sin auth | `auth.api:sanctum` | Solo el landlord dueño debería poder tocarlas |
| `/favorites` CRUD | Sin auth | `auth.api:sanctum` | Estado por-usuario, no debería ser accesible sin sesión |
| `/payments` CRUD | Sin auth | `auth.api:sanctum` | Dato más sensible del sistema |
| `/ratings` GET/PUT/DELETE | Sin auth (POST/index sí protegidos) | `auth.api:sanctum` | Inconsistencia: crear estaba protegido, editar/borrar no |
| `/cancelations_polices` GET | Sin auth | Público (sin cambio) | Texto informativo de política |
| `/cancelations_polices` POST/PUT/DELETE | Sin auth | `auth.api:sanctum` | Solo landlord/admin debería editarlas |
| `/store_moderation` CRUD | Sin auth | `auth.api:sanctum` + `role:admin` | Cola de moderación — dato interno |

**Nota técnica — por qué `auth.api:sanctum` y no `auth:sanctum`:** el middleware `Authenticate` estándar de Laravel intenta redirigir a una ruta con nombre `login` cuando el cliente no manda `Accept: application/json`; esta API no tiene esa ruta nombrada, así que cualquier petición sin ese header contra una ruta protegida con `auth:sanctum` devolvía **500 con stack trace** en vez de 401 (grave si `APP_DEBUG=true` en producción, que es el default de `.env.example`). El proyecto ya tenía la solución para esto — el alias `auth.api` (clase `ApiAuthenticate`), usado en `/me`, `/logout`, `/ratings`, `/reports` — simplemente no se usaba de forma consistente.

**Actualización posterior a la Fase 4:** las rutas que en su momento quedaron con `auth:sanctum` plano (`/profile`, `/password`, `/sessions`, `/reservations`, `/conversations`, `/notifications`, `/account`, `/tenant/reservations`) se corrigieron también a `auth.api:sanctum`, mismo patrón, mismo riesgo cerrado. Ya no queda ninguna ruta con `auth:sanctum` en el proyecto — todas usan `auth.api:sanctum`. Verificado con `curl` sin header `Accept` contra el servidor real: las 6 rutas antes afectadas (`/profile`, `/sessions`, `/landlord/reservations`, `/conversations`, `/notifications`, `/account`) devuelven 401 limpio (`{"message":"Unauthenticated."}`), y una sesión autenticada real sigue funcionando (200 en `/profile` con token válido). 98/98 tests, Pint/PHPStan/PHPMD limpios.

**Middleware nuevo:** `App\Http\Middleware\EnsureUserHasRole` (alias `role`), registrado en `bootstrap/app.php`. Responde 403 JSON si el usuario autenticado no tiene uno de los roles permitidos.

**Tests de regresión:** `AdminTest`, `StoreModerationTest` (nuevos) + `UserTest`, `LandlordsTest`, `TenantsTest`, `PaymentsTest`, `FavoritesTest` (actualizados desde la Fase 0 para reflejar el nuevo comportamiento a propósito). Cada endpoint recién protegido tiene su caso de "401 sin token" y su caso de "200/201 con sesión válida".

### Fase 1 — Limpieza ✅ Implementada
- Renombrar archivos de controlador (`storeRoomsController.php` → `StoreRoomsController.php`, ídem `storePricesController.php`) — adelantado a la Fase 0 porque Composer ya lo reportaba roto.
- `Users.php` y `frontend/src/assets/firebaseConfig.js` eliminados: cero referencias en todo el código, confirmado por grep antes de borrar.
- **La tabla `users` no era limpieza neutra** — al intentar borrarla se descubrió que `favorites.user_id` y `admin.user_id` tenían su FK apuntando a `users` en vez de `user` (mismo bug del hallazgo #4, dos instancias más). Borrar la tabla sin arreglar esas FKs habría roto el esquema en Postgres (producción). Se presentó la disyuntiva al usuario, que eligió arreglar de raíz:
  - Migraciones nuevas `fix_favorites_user_id_foreign_key` y `fix_admin_user_id_foreign_key` (no se editaron las migraciones originales ya aplicadas, para no romper el historial en entornos donde ya corrieron).
  - Corregidas las 5 reglas de validación que apuntaban a `users`/`exists:users,id` en vez de `user`/`exists:user,id`: `AuthController::register`, `TenantsController::store`/`update`, `NotificationsController::store`, `ReportsController::update`.
  - Migración `drop_users_table` (con `down()` que la recrea, para no romper rollback).
  - De paso apareció un sexto bug no relacionado con `users`: `NotificationsController` importaba `App\NotificationType` (namespace inexistente) en vez de `App\Enums\NotificationType` — error fatal en cuanto se invocaba `POST /notifications`. Corregido por ser un fix mecánico de una línea, mismo criterio que el renombrado de archivos de la Fase 0.
  - **Resultado**: Favorites (creación aún falla por el bug #2 de campo `store_id`/`store_room_id`, sin corregir — no estaba en el alcance acordado), Admin, Tenants, registro con email duplicado y notificaciones ahora funcionan correctamente donde antes fallaban siempre. 78/78 tests, PHPStan baseline bajó de 59 a 57 errores (los 2 resueltos).

### Fase 2 — Extraer validación y autorización (backend) ✅ Implementada
Se identificaron dos patrones de fallo de validación distintos ya existentes en el código, y se preservó cada uno para no alterar el contrato de la API:

- **Patrón A** (11 endpoints: `AuthController::register`, `ConversationController::store`, `NotificationsController::store`, `RatingsController::store`, `ReservationsController::store`/`updateStatus`, `MessageController::store`, `ProfileController::update`, `SecurityController::changePassword`, `ReportsController::store`/`updateStatus`) — usaban `$request->validate()` directo, que ya falla con el 422 estándar de Laravel. Se convirtieron a `FormRequest` inyectados de verdad (`App\Http\Requests\*`), comportamiento idéntico garantizado porque ambos mecanismos lanzan la misma `ValidationException`.
- **Patrón B** (13 endpoints sobre `ApiController::storeModel`/`updateModel`, más `StorePhotoController::store`) — responden 400 con una forma JSON propia si la validación falla. Inyectar un `FormRequest` ahí habría cambiado el status a 422 y la forma de la respuesta. En su lugar, las reglas se movieron a clases "bolsa de reglas" (mismo namespace `App\Http\Requests`, pero **no** extienden `FormRequest` — llevan un comentario explícito de por qué) que el controlador instancia manualmente: `(new StoreXRequest())->rules()`.
- **Excepción documentada**: `AuthController::login` se dejó intacto — su `$request->validate()` está envuelto en un `try/catch(\Exception $e)` que hoy devuelve 500 en validaciones fallidas (`ValidationException` es una `\Exception`). Inyectar un FormRequest ahí habría cambiado ese 500 a 422 porque la validación ocurriría en el pipeline de middleware, fuera del try/catch.
- **Policies**: `ConversationPolicy::view` (formaliza el check duplicado que vivía en `MessageController::authorizeConversation()` y en `markRead()`) y `ReservationsPolicy::updateStatus` (formaliza el check manual de `ReservationsController::updateStatus`, preservando que "no tienes perfil de landlord" siga siendo 404 vía `firstOrFail()` y solo "no eres el dueño" sea 403 vía la Policy). El check de rol de `ReportsController::updateStatus` (`if ($request->user()->role !== 'admin')`) se movió a middleware de ruta (`role:admin`), consistente con el patrón ya usado en la Fase 0.5, en vez de convertirse en Policy.
- 82/82 tests (4 nuevos: ownership de reservas y de reportes), PHPStan baseline se mantuvo en 57 (los accesos a propiedades dinámicas solo se reubicaron a las Policies, no aumentaron), Pint y PHPMD limpios. Verificado también con `curl` que la forma de las respuestas de error no cambió entre patrones (422 vs 400 vs 403).

### Fase 3 — Backend, dominio por dominio (Strangler Fig) ✅ Implementada (con alcance revisado)
Al llegar a esta fase se revisó el orden original contra el código real: `CancelationsPolices`, `StoreModeration`, `Favorites`, `StorePhoto`, `StoreDisponibility`, `StorePrices` y `StoreRooms` son delegadores CRUD puros hacia `ApiController` sin ninguna regla de negocio, efecto secundario o notificación (confirmado leyendo cada uno; la "moderación" real de bodegas ni siquiera pasa por `StoreModerationController` — ocurre editando `StoreRooms.publication_status` directo). Extraer un Service ahí habría significado copiar a mano la lógica genérica de `ApiController` (transacciones, formas 400/404/201, auto-create de relaciones) sin ganar nada arquitectónico y con riesgo real de divergencia. Se presentó la disyuntiva al usuario, que eligió enfocar el esfuerzo donde había lógica real:

1. **`RatingsService`** — la regla "no calificar la misma bodega dos veces" (antes inline en el controlador, sin ningún test que la cubriera). `RatingsController::update` también quedó movido a `UpdateRatingRequest` de paso (se había escapado de la Fase 2).
2. **`ReportService`** — creación de reporte + adjuntar evidencias + fan-out de notificaciones a todos los admins y al landlord dueño de la bodega (con el caso borde de no duplicar si el landlord también es admin — sin test antes de esto).
3. **`ReservationService`** — el más grande y riesgoso: detección de conflictos de fechas, confirmación con cascada de cancelación automática de otras reservas `pending` que se solapan, y las notificaciones de cada transición. La cascada de cancelación no tenía ningún test antes de esta extracción.
4. **`AuthService`** (token + metadata, deduplicado entre `login`/`register`, que vivía copiado idéntico en ambos) y **`UserRegistrationService`** (alta automática de perfil Landlord/Tenant según rol) — el núcleo del sistema, tocado al final.

Cada Service quedó con sus propios tests unitarios (`tests/Unit/*ServiceTest.php`), independientes de HTTP — la ventaja concreta de haberlos extraído. 98/98 tests, verificado también con `curl` real (login, registro anónimo, conflicto de reservas) contra el servidor levantado, no solo con la suite.

**Nota técnica:** `UserController::store` sigue pasando por `ApiController::storeModel`, que devuelve el ítem creado como `stdClass` (decodificado de JSON), no como modelo Eloquent — `UserRegistrationService::createProfileForRole()` exige un `User` real, así que el controlador re-consulta el modelo (`User::findOrFail($data->item->id)`) antes de llamar al servicio. Una consulta extra, aceptada a cambio de que el Service tenga una firma de tipos correcta y sea reusable/testeable con modelos reales.

### Fase 4 — Frontend: desacoplar estado y acceso a datos ✅ Implementada

**4a. `AuthContext`/`useAuth` ✅ Implementada**
- Se re-mapeó el uso real de `localStorage` antes de tocar nada: de 19 archivos que lo usan, solo 12 son sesión real (`auth_token`/`auth_user`); el resto (`PreguntaInicio1-7`, `Decision.tsx`, `Register.tsx`) usan claves distintas (`optionData`, `tempUser`) para persistir el wizard de formularios — un concern aparte, no se tocó.
- `src/context/authContextBase.ts` (tipos + `createContext`), `AuthContext.tsx` (`AuthProvider`, estado sincronizado con `localStorage`, `login()`/`logout()`), `useAuth.ts` (el hook) — separados en 3 archivos en vez de uno solo porque un único archivo exportando componente + hook dispara el lint `react-refresh/only-export-components`.
- Los 11 componentes reales migrados a `useAuth()`: `Protected`, `Role`, `login`, `logout`, `HeaderTendant`, `SidebarAdmin`, `LeodegaUI`, `BodegasArrendador`, `Dashboard/Layout`, `Dashboard/Tendant/Storage`, `PreguntaInicio7` (solo su lectura de `auth_user`).
- **Excepción a propósito:** `src/api/axios.ts` sigue leyendo `localStorage` directo — el interceptor de axios no es un componente React y no puede usar hooks.
- **Bug propio detectado y corregido en la verificación:** un `sed` con grupo de captura repetido (`\(\.\./\)*`) para reescribir imports solo conservó el último `../`, dejando dos archivos (`Dashboard/Tendant/Storage.tsx`, `PreguntaInicio7.tsx`, ambos a dos niveles de profundidad) con una ruta relativa rota. `tsc --noEmit` **no lo detectó** (no se investigó por qué); lo encontró Vite en runtime al levantar el dev server real, y se confirmó con los 7 specs de Cypress fallando de golpe. Corregido y re-verificado.
- Verificado con `tsc --noEmit`, `npm run build`, y los 7 specs de Cypress contra el stack real (backend + frontend levantados), no solo con tipos.

**4b. Capa `services/` ✅ Implementada**
- Se relevó el uso real de `api.*` antes de diseñar: 26 componentes, agrupados en 9 dominios (`auth`, `users`, `profile`, `notifications`, `storeRooms`, `reservations`, `reports`, `conversations`, `ratings`) según el endpoint que llaman, no según el componente que los usa.
- El relevamiento inicial (grep de `api\.(get|post|...)\(`) tuvo dos huecos reales, encontrados recién al migrar cada archivo: la subida de fotos en `PreguntaInicio7.tsx` (`POST /store-rooms/{id}/photos`) y las dos llamadas de `LeodegaUI.tsx` escritas en estilo encadenado (`api\n.get(...)` en vez de `api.get(...)` en una sola línea, que el regex no capturaba). Ambas se agregaron a `services/storeRooms.ts` y `services/reservations.ts` respectivamente.
- **Error propio corregido en el momento:** al mover la llamada de detalle de `LeodegaUI.tsx` al service, se agregó por error un `if (!id) return;` que no existía en el original — cambiaba el comportamiento (antes se llamaba igual con `id` posiblemente `undefined`). Se revirtió a una aserción de tipo (`id as string`) que preserva el comportamiento exacto sin agregar una condición nueva.
- Los 26 componentes migrados, verificados con `tsc --noEmit` en checkpoints por lote (no al final, para aislar errores rápido).

**4c. `Settings.tsx` y `Mensajes.tsx`/`MensajesTendant.tsx` ✅ Implementada**
- `diff` confirmó que `Mensajes.tsx` y `MensajesTendant.tsx` eran **idénticos byte a byte** salvo la ruta de import — no había ninguna lógica condicional por rol que "parametrizar" como preveía el plan original. Se eliminó el duplicado y las tres rutas (`/admin/mensajes`, `/arrendador/mensajes`, `/arrendatario/mensajes`) apuntan al mismo componente.
- `Settings.tsx` (614 líneas, 5 tabs) dividido en `Settings/{Perfil,Notificaciones,Seguridad,Pagos,Privacidad}Tab.tsx`. Ningún estado se comparte entre tabs, así que cada uno quedó autónomo. El único caso no trivial: la carga de sesiones activas en `SeguridadTab` dependía de `activeTab === "seguridad"` en el padre — al vivir ahora en un componente que solo se monta cuando esa pestaña está activa (por el `{activeTab === 'seguridad' && <SeguridadTab />}` del padre), un `useEffect` de montaje reproduce el mismo comportamiento exacto (recarga cada vez que se entra a la pestaña).
- Verificado con `tsc --noEmit`, `npm run build`, y los 7 specs de Cypress contra el stack real levantado — no solo con tipos. `eslint` se mantuvo en 82 problemas (sin deuda nueva).

### Fase 5 — Consolidación (evaluada, no ejecutada)

A diferencia de las Fases 0-4 —cada una cerrando un problema concreto ya verificado (huecos de seguridad, bugs de la tabla `users`, lógica de negocio sin test, estado disperso en 12 componentes)— los 3 ítems originales de esta fase se reevaluaron contra el estado actual del código antes de tocar nada, y los 3 resultaron ser alto riesgo / bajo beneficio en este momento:

- **API Resources de Laravel**: envolver cada respuesta en una clase `Resource` tocaría ~15-20 endpoints, con riesgo real de romper la forma exacta del JSON que ya cubren los characterization tests de las Fases 0-3, a cambio de un beneficio mayormente arquitectónico — no hay ninguna fuga de datos sensibles hoy (`User` ya oculta `password`/`remember_token` vía `$hidden`).
- **Reestructurar frontend a carpetas por dominio**: mover ~80 archivos y actualizar cientos de imports, cero beneficio funcional. La Fase 4 ya mostró en vivo el riesgo real de este tipo de movimiento masivo (un solo regex con captura repetida rompió temporalmente toda la app).
- **State manager (React Query/Zustand)**: el plan pedía explícitamente "evaluar si hace falta" antes de introducirlo. Con `AuthContext` + la capa `services/` ya en su lugar (Fase 4), no se observa fetching redundante ni prop-drilling doloroso de estado remoto entre componentes que lo justifique hoy.

Decisión: no ejecutar los 3 ahora. Quedan en backlog, condicionados a que aparezca una necesidad real (una fuga de datos concreta, dolor real de organización, o un patrón de fetching redundante observado) — no a completar la lista del plan original por completitud.

### Post-Fase 5 — Bugs pendientes y deuda de ESLint ✅ Implementada

Tras cerrar la Fase 5, quedaban tres ítems identificados durante el diagnóstico inicial pero fuera del alcance de las fases anteriores. Se abordaron los tres, con aprobación explícita del usuario para los dos que implican un cambio de comportamiento real:

- **Bug de Favorites (`store_id` vs `store_room_id`)**: `StoreFavoriteRequest`/`UpdateFavoriteRequest` validaban un campo `store_id` que no existe en el modelo `Favorites` (la columna real es `store_room_id`), por lo que todo `POST /favorites` fallaba con un error de validación. Corregido en las dos `FormRequest` clases. Test de characterization actualizado: el test que documentaba el bug (`test_store_currently_fails_with_server_error_due_to_field_name_mismatch`) se reemplazó por `test_store_creates_favorite_when_authenticated`, que ahora afirma un 201 y la fila creada en base.
- **Bug de `SolicitudRevisarResponder.tsx`**: al rechazar una solicitud, el frontend enviaba `status: "resolved"` en vez de `status: "canceled"` — un reporte rechazado quedaba marcado como resuelto. Corregido el valor enviado.
- **Deuda de ESLint (82 → 0 errores)**: pagada por completo. El enfoque combinó eliminación de código muerto ya verificado sin referencias (`Description.tsx`, `Gallery.tsx`, `PriceBox.tsx`, funciones y estados nunca leídos), tipado de `any` a interfaces concretas compartidas entre componentes que consumen la misma forma de API (`StoreRoomDetail`, `StoreRoomSummary`, `LandlordReservation`, `AppNotification`, `ReportListItem`), y un helper `asApiError()` en `src/api/errors.ts` que reemplazó ~13 `catch (error: any)` repetidos. Quedan 2 warnings de `react-hooks/exhaustive-deps` (`PreguntaInicio5.tsx`, `PreguntaInicio6.tsx`), dejados a propósito como no bloqueantes, consistente con que ESLint es un gate no bloqueante desde la Fase 0.
- **Verificación final contra el stack real**: backend (`pint --test`, `phpstan analyse`, `phpmd`, `php artisan test` — 98/98 tests, 185 assertions) y frontend (`tsc --noEmit`, `npm run build`, `npm run lint` — 0 errores) todos en verde. Se levantó el stack real (`php artisan serve` + `vite`) con `migrate:fresh --seed` (con backup previo de `database.sqlite`, restaurado al terminar) y corrieron los 7 specs de Cypress: **7/7 en verde**.

### Post-verificación — Bug `store_rooms` vs `storeRooms` ✅ Implementada

El bug quedó documentado (no corregido) en el punto anterior; se corrigió después con aprobación explícita del usuario, en dos archivos:

- **`Calendario.tsx`** (calendario del arrendador): leía `reservation.store_rooms` (snake_case); el backend expone la relación Eloquent como `storeRooms` (camelCase, nombre del método en `Reservations::storeRooms()`). El label caía siempre al fallback `'Bodega'`. Corregido a `reservation.storeRooms`.
- **`Dashboard/Tendant/CalendarioTendant.tsx`** (calendario del arrendatario, endpoint `/tenant/reservations`): mismo bug exacto, mismo fallback (`'Reserva'`) siempre activo. Encontrado al revisar el primer fix — no estaba en el alcance original, se confirmó con el usuario antes de tocarlo. Corregido igual.
- Se limpió el campo `store_rooms` (muerto) de la interfaz `LandlordReservation` en `services/reservations.ts`, junto con el comentario que documentaba el bug ya resuelto.
- Verificado con `tsc --noEmit`, `npm run build` y `npm run lint` (se mantiene en 0 errores, 2 warnings preexistentes).

### Post-verificación — Tests unitarios de frontend (arranque) ✅ Implementada

No existía ningún framework de testing unitario en el frontend. Se configuró **Vitest + React Testing Library** (elegido por integrarse directo con la config de Vite ya existente, sin bundler/transform aparte) y se cubrió la primera tanda acordada con el usuario: lógica pura sin UI.

- **Setup**: `vitest`, `@testing-library/react`, `@testing-library/jest-dom`, `@testing-library/user-event`, `jsdom` como devDependencies (no agregan vulnerabilidades nuevas — las 26 preexistentes son todas transitivas de `vite`/`cypress`, verificado con `npm audit` antes/después). `vitest.config.ts` separado (no se tocó `vite.config.ts` de producción), reutiliza esa config vía `mergeConfig`. Scripts nuevos: `npm test` (`vitest run`) y `npm run test:watch`.
- Se dejó `globals: false` (default) a propósito — cada test importa `describe/it/expect/vi` explícitamente de `vitest` en vez de usar globals mágicos, así no hace falta tocar `tsconfig.app.json` (que gatea `npm run build` vía `tsc --noEmit`) para agregar tipos globales de test.
- **Cobertura agregada (22 tests, 5 archivos)**: `src/api/errors.test.ts` (la única con lógica real de branching: `asApiError` con error típico de axios, `null`, `undefined`, `Error` plano) y contratos de las 4 capas de servicio ya tipadas en la Fase 4 (`reservations`, `reports`, `notifications`, `storeRooms`) — verifican método HTTP, URL y payload exactos que cada función envía, mockeando el módulo `api/axios` con `vi.mock` + `vi.hoisted`. Esto cubre exactamente el punto donde ya aparecieron 2 bugs reales (Favorites, `store_rooms`/`storeRooms`): un typo de endpoint o de payload ahora rompe un test en vez de descubrirse en producción.
- No se tocó código de producción para esto — es una adición pura. Verificado que `tsc --noEmit`, `npm run lint` (0 errores) y `npm run build` siguen en verde con los archivos de test incluidos, y que los `.test.ts` no terminan en el bundle de `dist/`.
- Quedan fuera de esta tanda (pendiente si se retoma): `AuthContext`/`useAuth` y un componente de UI representativo — ambos requieren render con Testing Library, no solo funciones puras.

### Post-verificación — Tests unitarios de frontend (segunda tanda: render con Testing Library) ✅ Implementada

Continuación directa de la tanda anterior, cubriendo los dos casos que quedaban pendientes por requerir render (no solo funciones puras).

- **`src/context/AuthContext.test.tsx`** (6 tests): `AuthProvider`/`useAuth` vía `renderHook`. Cubre hidratación inicial desde `localStorage` (vacío, con datos, con JSON corrupto → `null` por el `catch` ya existente en `readStoredUser`), `login()` escribiendo en `localStorage` y actualizando estado, `logout()` limpiando ambos, y que `useAuth()` lanza el error esperado (`"useAuth debe usarse dentro de <AuthProvider>"`) fuera del provider.
- **`src/Dashboard/Reportes.test.tsx`** (5 tests), como componente de UI representativo del patrón fetch → mapeo → render → filtros ya tocado en la limpieza de ESLint: estado de carga, render de filas con el label de `estado` mapeado correctamente, filtro por tipo, botón "Reiniciar" limpiando el filtro activo, y manejo del `catch` cuando el fetch falla (verifica `console.error` y que `loading` se apague igual).
- **Bug propio encontrado y corregido en el momento**: el primer intento de `Reportes.test.tsx` dejaba el DOM de un test montado sobre el siguiente (dos renders acumulados en `document.body`), porque `@testing-library/react` no hace *auto-cleanup* entre tests salvo que se registre explícitamente — cosa que solo pasa sola con `globals: true` en Vitest, y acá se dejó `globals: false` a propósito (ver tanda anterior). Se agregó `afterEach(() => cleanup())` en `src/test/setup.ts`, que es lo que ese modo requiere.
- **33/33 tests en verde** (7 archivos). Verificado también que `tsc --noEmit`, `npm run lint` (0 errores) y `npm run build` se mantienen limpios con estos archivos incluidos.

### Verificación final consolidada ✅

Con todos los pendientes cerrados (bugs de Favorites, `SolicitudRevisarResponder`, `store_rooms`/`storeRooms` en los dos calendarios, deuda de ESLint, y las dos tandas de tests unitarios), se corrió una última pasada completa contra el estado final del código:

- **Backend**: `pint --test` (184 archivos, sin cambios), `phpstan analyse` (0 errores), `phpmd` (0 violaciones), `php artisan test` (**98/98**, 185 assertions).
- **Frontend**: `tsc --noEmit` (limpio), `vitest run` (**33/33**), `npm run lint` (**0 errores**, 2 warnings preexistentes de `react-hooks/exhaustive-deps` dejados a propósito), `npm run build` (compila).
- **Stack real**: `migrate:fresh --seed` (con backup previo de `database.sqlite`, restaurado al terminar) + `php artisan serve` + `vite` levantados, **7/7 specs de Cypress en verde**.
- Todo el trabajo descrito en este documento, desde la Fase 0 hasta acá, queda verificado en conjunto — no solo fase por fase de forma aislada.

Único punto que queda deliberadamente fuera (no es un pendiente olvidado, es una decisión tomada y reafirmada dos veces): los 3 ítems de Fase 5 (API Resources, reestructura del frontend por dominio, state manager), evaluados como alto riesgo / bajo beneficio en el estado actual del código. Quedan en backlog, condicionados a que aparezca una necesidad real.

---

## 4. Estrategia de Arquitectura Objetivo

Dado el tamaño real del proyecto (~12k líneas totales, alcance académico), **Clean Architecture/Hexagonal completa sería sobre-ingeniería**. Se propone una arquitectura en capas pragmática:

- **Backend:** `Controller` (delgado) → `FormRequest` (validación) → `Service` (reglas de negocio) → Eloquent (persistencia) → `API Resource` (respuesta). `Policies` para autorización. Eloquent se mantiene como ORM directo salvo en los módulos con lógica de negocio real (`Reservations`, `Auth/User`, `Payments`), donde se justifica un `Repository` explícito para poder testear la lógica sin DB.
- **Frontend:** estructura por *feature* (`reservations/`, `messages/`, `storeRooms/`) en vez de por tipo de archivo; capa `services/` (API tipada), `hooks/` (lógica), `context/AuthContext` (única fuente de verdad de sesión/rol), componentes de presentación puros.

Lo que falta hoy no es "más capas": es sacar la lógica de negocio y la autorización de donde está enterrada (controladores genéricos y componentes gigantes) y centralizar la sesión/rol en un solo lugar.

---

## 5. Estrategia de Testing de Regresión

- **Backend:** Feature test (characterization) por endpoint *antes* de tocarlo — capturar el JSON de respuesta actual como referencia ("golden"). `phpstan`/`psalm` en CI para detectar errores de tipo al mover código entre capas.
- **Frontend:** ampliar los 5 specs Cypress existentes para cubrir Reservations/Settings/Mensajes antes de refactorizarlos; introducir Vitest + React Testing Library para la lógica que se extraiga a hooks/services (hoy no existe testing unitario en frontend).
- **Regla de mergeo:** ningún PR de refactor se aprueba sin (a) characterization tests en verde antes y después, (b) CI verde, (c) confirmación explícita de "mismo comportamiento observable, distinta estructura interna".
- Sin big-bang rewrite ni feature flags a esta escala — Strangler Fig incremental, un módulo por PR.
