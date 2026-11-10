# Casos de regresión 7.2E.12

- `patata` y `patatas` deben localizar las mismas referencias pertinentes.
- `papa` y `papas` deben localizar productos de patata, sin incluir papaya por la coincidencia de subcadena.
- `croqueta` y `croquetas` deben conservar equivalencia.
- `aros de cebolla` no debe devolver cebolla sin aros.
- `6 x 1 kg` debe seguir filtrando por presentación.
- Referencia exacta debe conservar ceros iniciales.

Ejecutar estos casos sobre el endpoint HTTP real antes de desplegar.
