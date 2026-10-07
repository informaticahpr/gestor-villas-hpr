<?php

namespace App\Services\Importacion;

/**
 * Reglas para pasar el nombre de propietario que trae un Excel a lo que guarda el sistema.
 *
 * - Si la villa esta a nombre de varias personas ("X / Y", "X y Y", "X & Y", "X, Y", "X e Ismael...",
 *   "X (Y)") se registra solo la primera; el texto completo va a la observacion de la villa.
 * - "Bessy encargada" no es titular: se anota aparte en la observacion.
 * - El nombre se parte en NOMBRES / APELLIDOS con una lista de nombres de pila comunes
 *   (ej. "Ana Maria Sanchez Robles" -> ANA MARIA / SANCHEZ ROBLES).
 * - "Mario & Suli de Soto": el primero no trae apellido y toma el del ultimo (MARIO / DE SOTO).
 */
class NombrePropietario
{
    /** Empresas: el nombre completo queda en NOMBRES y no se parte en varias personas. */
    private const REGEX_EMPRESA = '/\b(S\.?\s?A\.?|S\.?\s?DE\s?R\.?\s?L|GRUPO|INVERSIONES|INVERNOSA|CORREDURIA|PROMOTU?OU?R|INTERAMERICANA|FICOHSA|CONSTANCIA|BANCO|BAC|OPTICA|HOTEL|CORPORACION|CORPORATION|INMOBILIARI[AO]|DESARROLLOS|ASEGURADORA|OABI|PETROSEAL)\b/u';

    private const PARTICULAS = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'DA', 'VAN', 'VON'];

    /** Nombres de pila frecuentes (sin tildes, en mayusculas). Sirve para saber donde empiezan los apellidos. */
    private const NOMBRES_DE_PILA = [
        'AARON', 'ABRAHAM', 'ADA', 'ADALID', 'ADOLFO', 'ADRIANA', 'AGUSTINA', 'AIDA', 'ALBERTO', 'ALDO', 'ALEJANDRA', 'ALEJANDRO',
        'ALFONSO', 'ALFREDO', 'ALICIA', 'ALLAN', 'AMANDA', 'ANA', 'ANN', 'ANDREA', 'ANDRES', 'ANGEL', 'ANGELA', 'ANGELES', 'ANTONIA', 'ANTONIO',
        'ANUAR', 'ARMANDO', 'ARTURO', 'AURORA', 'AZAEL', 'BARBARA', 'BEATRIZ', 'BERNARDA', 'BESSY', 'BIANKA', 'BLANCA', 'CAMILO',
        'CARLA', 'CARLO', 'CARLOS', 'CARMEN', 'CAROL', 'CAROLINA', 'CECILIA', 'CESAR', 'CESARIO', 'CESIA', 'CHRISTINE', 'CHRYSTIAN',
        'CINDY', 'CLAUDIA', 'CONRADO', 'CONSTANTINO', 'CRISTOBAL', 'CYNDI', 'DANIEL', 'DANILO', 'DARWIN', 'DAVID', 'DAYANA', 'DAYSI',
        'DENISSE', 'DIANA', 'DIGNA', 'DOLORES', 'DONALDO', 'DORIS', 'DULCE', 'EDGARDO', 'EDITH', 'EDMONDO', 'EDUARDO', 'EDWIN',
        'EFRAIN', 'ELBA', 'ELENA', 'ELIDA', 'ELISEL', 'ELIZABETH', 'ELSA', 'EMILIA', 'EMILIO', 'ENRIQUE', 'ERICK', 'ERIKA', 'ERMIS',
        'ERNELIA', 'ERNESTO', 'ESPERANZA', 'ESTER', 'ETHON', 'EUGENIA', 'EYBI', 'FAMEL', 'FARINA', 'FELIPE', 'FELIX', 'FERNANDO',
        'FLOR', 'FLORIDALMA', 'FRANCISCO', 'FRANKLIN', 'GABRIEL', 'GERARDO', 'GEOVANY', 'GEZA', 'GILBERTO', 'GILDA', 'GLORIA',
        'GUSTAVO', 'HAROL', 'HECTOR', 'HENA', 'HERIBERTO', 'HERNAN', 'HILDA', 'HIRAN', 'HOLLIS', 'HUGO', 'HUMBERTO', 'ILIANA',
        'IRIS', 'IRMA', 'ISRAEL', 'ISSA', 'IVONNE', 'JACINTA', 'JACOBO', 'JAVIER', 'JEAN', 'JESSICA', 'JESSIE', 'JESSIKA', 'JESUS',
        'JEWEL', 'JHONY', 'JOHANA', 'JOOLEN', 'JORGE', 'JOSE', 'JOVANI', 'JOSEPH', 'JOSUE', 'JUAN', 'JUDITH', 'JULIO', 'KARINA', 'KARLA',
        'KARLO', 'KATTY', 'LEANDRO', 'LETICIA', 'LIGIA', 'LILIANA', 'LINDA', 'LINDOLFO', 'LINO', 'LIZETH', 'LOLANDA', 'LORELI',
        'LORENA', 'LORILLE', 'LUCAS', 'LUIS', 'LUISA', 'LUZ', 'MADELEINE', 'MAESSE', 'MAGDALENA', 'MANUEL', 'MARCIO', 'MARCO',
        'MARCOS', 'MARDEN', 'MARGARITA', 'MARGO', 'MARIA', 'MARIANA', 'MARINA', 'MARIBEL', 'MARIELA', 'MARIETA', 'MARIO', 'MARITZA',
        'MARIZOL', 'MARLENY', 'MARLON', 'MARTHA', 'MARTIN', 'MARY', 'MAUDI', 'MAURICE', 'MAURICIO', 'MAYRA', 'MELVIN', 'MERCI',
        'MERLYN', 'MERY', 'MICHAEL', 'MIGUEL', 'MIRIAN', 'MONICA', 'NELSON', 'NELVIN', 'NESTOR', 'NEFTALI', 'NICK', 'NICOLAS',
        'NIDEA', 'NILDA', 'NIVIDA', 'NOEMY', 'NORA', 'NORIS', 'NORMA', 'NORTHON', 'ODIR', 'OFELIA', 'OLGA', 'OLINDA', 'ORALIA',
        'ORIEL', 'OSCAR', 'OVIDIO', 'PABLO', 'PATRICIA', 'PATRICK', 'PAULA', 'PERLA', 'RAFAEL', 'RAMON', 'RAQUEL', 'RENATO', 'REYNALDO',
        'RICARDO', 'RIGOBERTO', 'RINA', 'RITA', 'RIVA', 'ROBERTO', 'RODOLFO', 'ROLANDO', 'ROMMEL', 'ROSA', 'ROSALIA', 'ROSANY',
        'ROSARIO', 'RUMILDA', 'SABASTIAN', 'SALOMON', 'SALUSTIANO', 'SANDRA', 'SANTIAGO', 'SARA', 'SAUL', 'SEBASTIAN', 'SERGIO',
        'SILVIA', 'SONIA', 'SREYA', 'STEFAN', 'SULI', 'SUSAN', 'SUSANA', 'SUYAPA', 'TANIA', 'ULICES', 'VERONICA', 'VICTOR',
        'VIRGINIA', 'VILMA', 'VLADIMIR', 'WALESKA', 'WALTER', 'WALTHER', 'WILKIN', 'WLADIMIR', 'XIOMARA', 'YANNETTE', 'YASER',
        'YETTY', 'YOLANDA', 'YOLANY', 'YOVANNY', 'ZULEMA',
    ];

    /**
     * Separa el texto del Excel en titulares (personas o empresas a cuyo nombre esta la villa) y notas
     * que no son titulares (ej. "Bessy encargada").
     *
     * @return array{titulares: list<string>, notas: list<string>}
     */
    public static function titulares(string $texto): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));
        $texto = preg_replace('/\s+\b(TH|[A-Z])-?\d{1,2}[A-C]?$/u', '', $texto); // "Roberto Cesario Da Silva TH-15"
        $texto = str_replace('*', '', $texto);
        // "Promotour (Erick Gonzalez)": lo de entre parentesis es otra persona
        $texto = preg_replace('/\s*\(([^)]+)\)/u', ' / $1', $texto);

        $titulares = [];
        $notas = [];
        foreach (preg_split('/\s*\/\s*/u', $texto) as $parte) {
            $partes = self::esEmpresa($parte)
                ? [$parte]
                // ", " "&" " y " " e " (antes de i/hi) y ". " detras de una palabra (no de una inicial como "E.")
                : preg_split('/\s*,\s*|\s*&\s*|\s+y\s+|\s+e\s+(?=h?i)|(?<=[a-zñ]{2})\.\s+/iu', $parte);
            foreach ($partes as $p) {
                $p = trim($p, " .,-\t");
                if ($p === '') {
                    continue;
                }
                if (preg_match('/\bencargad[oa]\b/iu', $p)) {
                    $notas[] = 'ENCARGADO(A): '.self::mayusculas(trim(preg_replace('/\bencargad[oa]\b/iu', '', $p)));

                    continue;
                }
                $titulares[] = $p;
            }
        }

        return ['titulares' => $titulares, 'notas' => $notas];
    }

    /**
     * Primer titular del texto como NOMBRES / APELLIDOS, mas la observacion para la villa si hay
     * varios titulares o notas.
     *
     * @return array{clave: string, NOMBRES: string, APELLIDOS: string, observacion: ?string}
     */
    public static function propietario(string $texto): array
    {
        ['titulares' => $titulares, 'notas' => $notas] = self::titulares($texto);
        $primero = $titulares[0] ?? $texto;

        // "Mario & Suli de Soto": el primero no trae apellido, se toma el del ultimo titular
        $prestado = null;
        if (count($titulares) > 1 && ! self::esEmpresa($primero) && self::soloNombresDePila($primero)) {
            $ultimo = self::partir(end($titulares));
            $prestado = $ultimo['APELLIDOS'] !== '' ? $ultimo['APELLIDOS'] : null;
        }
        $persona = self::partir($primero, $prestado);

        $observacion = [];
        if (count($titulares) > 1) {
            $observacion[] = 'VILLA A NOMBRE DE: '.self::mayusculas(implode(' / ', $titulares));
        }
        array_push($observacion, ...$notas);

        return $persona + ['observacion' => $observacion ? implode('. ', $observacion) : null];
    }

    /**
     * Nombre de una sola persona -> NOMBRES / APELLIDOS. Empresas y nombres de una palabra quedan
     * completos en NOMBRES.
     *
     * @return array{clave: string, NOMBRES: string, APELLIDOS: string}
     */
    public static function partir(string $nombre, ?string $apellidoPrestado = null): array
    {
        $limpio = self::mayusculas($nombre);
        $palabras = explode(' ', $limpio);
        $n = count($palabras);

        if (self::esEmpresa($limpio) || $n === 1) {
            [$nombres, $apellidos] = [$limpio, $apellidoPrestado ? self::mayusculas($apellidoPrestado) : ''];
        } elseif ($apellidoPrestado !== null && self::soloNombresDePila($limpio)) {
            [$nombres, $apellidos] = [$limpio, self::mayusculas($apellidoPrestado)];
        } else {
            $k = self::cantidadDeNombres($palabras);
            [$nombres, $apellidos] = [implode(' ', array_slice($palabras, 0, $k)), implode(' ', array_slice($palabras, $k))];
        }

        return ['clave' => self::clave($limpio), 'NOMBRES' => mb_substr($nombres, 0, 60), 'APELLIDOS' => mb_substr($apellidos, 0, 60)];
    }

    /**
     * Clave para reconocer a la misma persona escrita distinto en varias villas: sin tildes, sin
     * puntos, letras dobles simples y z = s ("Roberto Michelletti" = "Roberto Micheletti",
     * "Luis Peres" = "Luis Perez").
     */
    public static function clave(string $nombre): string
    {
        $c = self::sinTildes(self::mayusculas($nombre));
        $c = preg_replace('/[^A-Z ]/', '', str_replace('Z', 'S', $c));
        $c = preg_replace('/([A-Z])\1+/', '$1', $c);

        return trim(preg_replace('/\s+/', ' ', $c));
    }

    public static function esEmpresa(string $texto): bool
    {
        return (bool) preg_match(self::REGEX_EMPRESA, self::sinTildes(self::mayusculas($texto)));
    }

    /** Cuantas de las primeras palabras son nombres de pila (el resto son apellidos). */
    private static function cantidadDeNombres(array $palabras): int
    {
        $n = count($palabras);
        if ($n === 2) {
            return 1;
        }
        $esNombre = fn (int $i) => isset($palabras[$i]) && (self::esNombreDePila($palabras[$i]) || self::esInicial($palabras[$i]));
        $esParticula = fn (int $i) => isset($palabras[$i]) && in_array($palabras[$i], self::PARTICULAS, true);

        // "Maria del Carmen Miranda", "Maria de los Angeles Santos": el nombre sigue por las particulas
        // si despues viene otro nombre de pila; en "Mirian de Espinal" la particula ya es del apellido
        if ($esParticula(1)) {
            $k = 1;
            while ($k < $n - 1 && $esParticula($k)) {
                $k++;
            }

            return $esNombre($k) ? min($k + 1, $n - 1) : 1;
        }
        if ($n === 3) {
            return $esNombre(1) ? 2 : 1;
        }
        // 4 o mas: dos nombres, salvo "Alicia Mejia de Calona" (el segundo ya es apellido)
        if (! $esNombre(1) && $esParticula(2)) {
            return 1;
        }

        return $esNombre(2) && $n >= 5 ? 3 : 2;
    }

    private static function soloNombresDePila(string $texto): bool
    {
        $palabras = explode(' ', self::mayusculas($texto));

        return count($palabras) <= 2 && collect($palabras)->every(fn ($p) => self::esNombreDePila($p) || self::esInicial($p))
            || count($palabras) === 1;
    }

    private static function esNombreDePila(string $palabra): bool
    {
        return in_array(self::sinTildes($palabra), self::NOMBRES_DE_PILA, true);
    }

    private static function esInicial(string $palabra): bool
    {
        return (bool) preg_match('/^\p{L}\.?$/u', $palabra);
    }

    private static function mayusculas(string $texto): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $texto)), 'UTF-8');
    }

    private static function sinTildes(string $texto): string
    {
        return strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
    }
}
