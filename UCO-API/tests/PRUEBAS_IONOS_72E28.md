# Validación pendiente en IONOS (entorno de pruebas)

1. Antes de publicar: crear respaldo y comprobar que `private/` queda fuera de la raíz web.
2. Comprobar `php -m` para `mbstring` y `curl`; revisar permisos de `private/logs`.
3. Probar GET → 405, POST `text/plain` → 415, cuerpo >12 KB → 413.
4. Con clave y configuración válidas, probar POST JSON válido → respuesta de OpenAI (no se puede garantizar 200: depende del servicio y del prompt).
5. Repetir 11 peticiones válidas desde una IP de prueba en 60 segundos; la undécima debe devolver 429 y cabecera `Retry-After: 60`. Evitar usar una IP compartida por clientes reales.
6. Confirmar que pedidos, carrito y búsqueda local siguen funcionando, y que ninguna solicitud general expone datos comerciales.
7. No publicar el proyecto sin comprobar el paso 4 y el efecto de IP compartida.

No ejecutar pruebas de carga sobre producción. Las pruebas locales no sustituyen a las pruebas HTTP en IONOS.
