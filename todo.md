[] hacer una función para actualizar los valores de las cuentas pare en update, javascript
-- seria bueno que cada account supiera cuales su padre, de hecho, lo sabe.

[] check github repository, push dsent work
[] PROBAR DISTINTOS CASOS ACTUALIZACIóN DE VALORES

[] cambiar angularjs to angular
[] pintar de colores registros segun estado

[] bug: ignore mayusculas en comparación
[] bug: al hacerse el balance por primera véz, se muestra todavía el mes anterior
[] focus onnew row when click en save boton
[] limpiar cache de fecha en javascript
[] hacer paginador
[] delete account
[] edit account: properties: only credit | only debit | annual | monthly
1.0 testear problema de conservación de valor de los accounts sincronizados
1.0 pintar de colores registros segun estado
11. ng-cloak class
12. estudiar uso de filter, some, map...
[] estandarizar nombre de funciones con guin bajo
[] hacer que el enter ejecute boton agregar
[] el catch del PHP no debe retornar 500, sino info de las filas cambiadas
[] ignorar fila vacia, no insertar
[] cuenta ingresos solo admite creditos y siempre positiva : [cuenta tipo solo credito, solo debito]
[] habilitar edición dia, y ¿mes?
[] sanitice GET
// Sanitize and validate the "param" query parameter as an integer
$param = filter_input(INPUT_GET, 'param', FILTER_SANITIZE_NUMBER_INT);
if ($param === false || $param === null) {
    // Invalid or missing "param" query parameter
    echo "Invalid or missing 'param' query parameter.";
} else {
    // The sanitized "param" query parameter as an integer
    echo "Sanitized 'param' query parameter: " . $param;
}

7. almacenar la información en local storage
6. set personal git repo
7. cambiar plural to/from account(s)
10. Delete account
[] Refactorizar para quitar dissable a todos los elementos,
   con CSS:  .disabled-form * {  pointer-events: none; }


03 20 | 3h : Se agregó inserción de accounts nuevas en php.
03 21 | 1h 30m : Se homogenizó nombre en ingles, inserción de cuentas en js .
03 29 | 1h : Resuelto problema sincronización de IDs accounts back/front.
           : Resuelto problema valor accounts sincronizados.
03 31 | 1h : Se prepara comando de inserción de columna.
04 12 | 1h : Ajuste columnas dates. Inserción en sheet.
           : Sincronización de sheet.
04 13 | 1h : Preprocesamiento de sheet, ajuste de valor y fechas.
05 09 | 1h : Definición de funciones para el cálculo de valores cuentas.
06 27 | 30m : Se obtienen valores de cuentas en sheet
06 27 | 2h : se refactoriza almacenamiento varias filas
           : se obtienen nombres de cuentas en javascript
           : se ordena en orden inverso
07 02 | 2h : se reordena en orden normal, se agrega campo vacio al final
           : se hace borrado y sincronización de borrado de filas
07 04 | 2h : edicion y sincronización de edición
           : obtención de get last date  
07 06 | 4h : balance y obtención de fechas
           : ajuste fechas balance e inserción
           : insercion de cuentas hijas
07 26 | 1h : actualización valores javascript
08 16 | 30m : Correción value en insert balance, local git repo created
08 28 | 2h : ajuste actualización valores en tabla, borrado de fila
08 29 | 2h : ajuste actualización valores en tabla, pintura de registros
09 21 | 30m : calculo valor cuenta compuesta, bug fixes
09 22 | 2h : almacenamientos cuentas con hijos en backend
09 25 | 30m : Corregido bug