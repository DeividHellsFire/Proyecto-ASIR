# language: es

Característica: Búsqueda de ejercicios en la biblioteca
  Como usuario de GymTrack
  Quiero buscar ejercicios en la biblioteca
  Para encontrar ejercicios que pueda utilizar en mis entrenamientos

  Escenario: Buscar un ejercicio existente
    Dado que estoy en la biblioteca de ejercicios
    Cuando busco "press banca"
    Entonces se muestran los ejercicios coincidentes
    Y se muestran sus datos e información disponible

  Escenario: Buscar un ejercicio inexistente
    Dado que estoy en la biblioteca de ejercicios
    Cuando busco "ejercicioinexistente123"
    Entonces se muestra el mensaje "No se han encontrado ejercicios"

  Escenario: Realizar una búsqueda vacía
    Dado que estoy en la biblioteca de ejercicios
    Cuando realizo una búsqueda sin introducir ningún término
    Entonces se muestra el mensaje "Introduce un término para buscar ejercicios"
