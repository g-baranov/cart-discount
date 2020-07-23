<?php

$items = [
    ['price' => 12312, 'quantity' => 4],
    ['price' => 91201, 'quantity' => 2],
    ['price' => 12312, 'quantity' => 2],
    ['price' => 15, 'quantity' => 3],
    ['price' => 119, 'quantity' => 7],
    ['price' => 119, 'quantity' => 7],
    ['price' => 119, 'quantity' => 3],
    ['price' => 119, 'quantity' => 1],
    ['price' => 142, 'quantity' => 2],
    ['price' => 42, 'quantity' => 10],
    ['price' => 1, 'quantity' => 3],
    ['price' => 12, 'quantity' => 9],
];

uasort($items, function ($a, $b) {
    return $b['price'] <=> $a['price'] ;
});
//$itemsSumMan = 257152;
$itemsSum = getItemsSum($items);

$discount = 50000;

$newItems = applyDiscount($items, $discount);
//foreach ($newItems as $key => $item) {
//    $oldSum = getItemSum($items[$key]);
//    $newSum = getItemSum($item);
//    echo "{$oldSum} -> {$newSum}\n";
//}
$itemsSumNew = getItemsSum($newItems);

echo "{$itemsSum} -> {$itemsSumNew}\n";
echo sprintf('%s -> %s',
    round($itemsSum - $discount),
    round($itemsSumNew + $discount, 2));

function applyDiscount(array $items, int $discount): array
{
    $itemsSum = getItemsSum($items);

    $itemsNew = [];
    foreach ($items as $item) {
        $item['price'] = $item['price'] - (($item['price'] / $itemsSum) * $discount);
        $itemsNew[] = $item;
    }

    return $itemsNew;
}

function applyDiscount2(array $items, int $discount) {

}

function getItemsSum(array $items): float
{
    return array_sum(array_map(fn($item) => getItemSum($item), $items));
}

function getItemSum(array $item)
{
    return round($item['price'] * $item['quantity'], 2);
}




/**
 * Метод выполняет пропорциональное распределение суммы в соответствии с заданными коэффициентами распределения.
 * Также может выполняться проверка полного деления суммы коэффициента на его количество. Например,
 * при нулевой точности для чётного количества штук товара было неправильно получить нечётную сумму
 * после распределения, - правильно немного увеличить сумму распределения (в ущерб пропорциональности),
 * чтобы добиться ровного распределения по количеству.
 * Используется, например, при распределении скидки равномерно по позициям корзины.
 * @param float $sum Распределяемая сумма
 * @param array $arCoefficients Массив коэффициентов распределения, где ключи - определённые значения,
 *    которые также будут возвращены в виде ключей результирующего массива. Значения - массив с ключами:
 *    "sum"   - величина коэффициента (сумма, а не цена)
 *    "count" - количество для коэффициента
 * @param int $precision Точность округления при распределении. Если передать 0,
 *    то все суммы после распределения будут целыми числами
 * @throws Exception Выбрасывается исключение в случае,
 *    если невозможно ровно распределить по заданным параметрам
 * @return array Массив, где сохранены ключи исходного массива $arCoefficients, а значения - массив с ключами:
 *    "init"  - начальная сумма, равная соответствующему входному коэффициенту
 *    "final" - сумма после распределения
 */
public function getProportionalSums(float $sum, array $arCoefficients, int $precision) : array
{
    $arResult = [];

    /**
     * @var float Сумма значений всех коэффициентов
     */
    $sumCoefficients = 0.0;

    /**
     * @var float Значение максимального коэффициента по модулю
     */
    $maxCoefficient = 0.0;

    /**
     * @var mixed Ключ массива для максимального коэффициента по модулю
     */
    $maxCoefficientKey = null;

    /**
     * @var float Распределённая сумма
     */
    $allocatedAmount = 0;

    foreach ($arCoefficients as $keyCoefficient => $coefficient) {
        if (is_null($maxCoefficientKey)) {
            $maxCoefficientKey = $keyCoefficient;
        }

        $absCoefficient = abs($coefficient['sum']);
        if ($maxCoefficient < $absCoefficient) {
            $maxCoefficient = $absCoefficient;
            $maxCoefficientKey = $keyCoefficient;
        }
        $sumCoefficients += $coefficient['sum'];
    }
    if (!empty($sumCoefficients)) {
        /**
         * @var float Шаг, который прибавляем в попытках распределить сумму с учётом количества
         */
        $addStep = (0 === $precision) ? 1 : (1 / pow(10, $precision));

        foreach ($arCoefficients as $keyCoefficient => $coefficient) {
            /**
             * @var boolean Флаг, удалось ли подобрать сумму распределения для текущего коэффициента
             */
            $isOk = false;

            /**
             * @var integer Количество попыток подобрать сумму распределения
             */
            $i = 0;

            // Далее вычисляем сумму распределения с учётом заданного количества
            do {
                $result = round(($sum * $coefficient['sum'] / $sumCoefficients), $precision) + $i * $addStep;

                // Проверим распределённую сумму коэффициента относительно его количества
                if (isset($coefficient['count']) && $coefficient['count'] > 0) {
                    if (round($result / $coefficient['count'], $precision) != ($result / $coefficient['count'])) {
                        // Не прошли проверку по количеству - ровно по заданному количеству не распределяется

                    } else {
                        $isOk = true;
                    }
                } else {
                    // Количество не задано, значит не проверяем распределение по количеству
                    $isOk = true;
                }

                $i++;
                if ($i > 100) {
                    // Мы старались долго. Пора признать, что ничего не выйдет
                    throw new Exception(
                        'Не удалось распределить сумму для коэффициента ' . $keyCoefficient
                    );
                }
            } while (!$isOk);

            // Если сюда дошли, значит удалось вычислить сумму распределения
            $arResult[$keyCoefficient] = [
                'init'  => $coefficient['sum'],
                'final' => (0 === $precision) ? intval($result) : $result,
                'count' => $coefficient['count']
            ];

            $allocatedAmount += $result;
        }

        if ($allocatedAmount != $sum) {
            // Есть погрешности округления, которые надо куда-то впихнуть
            $tmpRes = $arResult[$maxCoefficientKey]['final'] + $sum - $allocatedAmount;
            if (!isset($arResult[$maxCoefficientKey]['count'])
                || (isset($arResult[$maxCoefficientKey]['count']) && 1 === $arResult[$maxCoefficientKey]['count'])
                || (isset($arResult[$maxCoefficientKey]['count'])
                    && $arResult[$maxCoefficientKey]['count'] > 0
                    && (round($tmpRes / $arResult[$maxCoefficientKey]['count'], $precision) == ($tmpRes / $arResult[$maxCoefficientKey]['count']))
                )
            ) {
                // Погрешности округления отнесём на коэффициент с максимальным весом
                $arResult[$maxCoefficientKey]['final'] = (0 === $precision) ? intval($tmpRes) : $tmpRes;
            } else {
                // Погрешности округления нельзя отнести на коэффициент с максимальным весом
                // Надо подыскать другой коэффициент
                $isOk = false;
                foreach ($arCoefficients as $keyCoefficient => $coefficient) {
                    if ($keyCoefficient != $maxCoefficientKey) {
                        // Пробуем погрешность округления впихнуть в текущий коэффициент
                        $tmpRes = $arResult[$keyCoefficient]['final'] + $sum - $allocatedAmount;
                        if (!isset($arResult[$keyCoefficient]['count'])
                            || (isset($arResult[$keyCoefficient]['count']) && 1 === $arResult[$keyCoefficient]['count'])
                            || (isset($arResult[$keyCoefficient]['count'])
                                && $arResult[$keyCoefficient]['count'] > 0
                                && (round($tmpRes / $arResult[$keyCoefficient]['count'], $precision) == ($tmpRes / $arResult[$keyCoefficient]['count']))
                            )
                        ) {
                            // Погрешности округления отнесём на коэффициент с максимальным весом
                            $arResult[$keyCoefficient]['final'] = (0 === $precision) ? intval($tmpRes) : $tmpRes;
                            $isOk = true;
                            break;
                        }
                    }
                }
                if (!$isOk) {
                    throw new Exception('Не удалось распределить погрешность округления');
                }
            }
        }
    }

    return $arResult;
}