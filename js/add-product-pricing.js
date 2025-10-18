export function initializeAddProductPricingAutomation(root = document) {
    if (!root) {
        return;
    }

    const basePriceInput = root.getElementById('msrp_gross');
    if (!basePriceInput) {
        return;
    }

    const uvpNettoInput = root.getElementById('msrp_net');
    const usedPriceInput = root.getElementById('outlet_warranty_gross');

    const newArticleMonthlyInputs = {
        subscription_12_monthly: root.getElementById('subscription_12_monthly'),
        subscription_9_monthly: root.getElementById('subscription_9_monthly'),
        subscription_6_monthly: root.getElementById('subscription_6_monthly'),
        subscription_3_monthly: root.getElementById('subscription_3_monthly'),
        subscription_1_monthly: root.getElementById('subscription_1_monthly')
    };

    const newArticleUpfrontInputs = {
        subscription_12_upfront: root.getElementById('subscription_12_upfront'),
        subscription_9_upfront: root.getElementById('subscription_9_upfront'),
        subscription_6_upfront: root.getElementById('subscription_6_upfront'),
        subscription_3_upfront: root.getElementById('subscription_3_upfront'),
        subscription_1_upfront: root.getElementById('subscription_1_upfront')
    };

    const monthlyFormulaDivisors = {
        subscription_9_monthly: 0.83996799359,
        subscription_6_monthly: 0.66661374821,
        subscription_3_monthly: 0.56750912285,
        subscription_1_monthly: 0.38176197836
    };

    const parseNumber = (value) => {
        if (typeof value !== 'string') {
            return null;
        }
        const normalised = value.replace(',', '.');
        const parsed = parseFloat(normalised);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const formatPrice = (value) => (Number.isFinite(value) ? value.toFixed(2) : '');

    const roundToIntegerMinusCent = (value) => {
        if (!Number.isFinite(value)) {
            return null;
        }
        const rounded = Math.round(value);
        const adjusted = Math.max(0, rounded - 0.01);
        return parseFloat(adjusted.toFixed(2));
    };

    const updateDerivedFields = () => {
        const basePrice = parseNumber(basePriceInput.value);

        if (basePrice === null) {
            if (uvpNettoInput) {
                uvpNettoInput.value = '';
            }
            if (usedPriceInput) {
                usedPriceInput.value = '';
            }
            const allDerivedInputs = {
                ...newArticleMonthlyInputs,
                ...newArticleUpfrontInputs
            };
            Object.values(allDerivedInputs).forEach((input) => {
                if (input) {
                    input.value = '';
                }
            });
            return;
        }

        if (uvpNettoInput) {
            uvpNettoInput.value = formatPrice(basePrice / 1.19);
        }

        const usedPrice = roundToIntegerMinusCent(basePrice * 0.84);
        if (usedPriceInput && usedPrice !== null) {
            usedPriceInput.value = formatPrice(usedPrice);
        }

        const twelveMonthPrice = roundToIntegerMinusCent(basePrice * 0.07);
        if (newArticleMonthlyInputs.subscription_12_monthly && twelveMonthPrice !== null) {
            newArticleMonthlyInputs.subscription_12_monthly.value = formatPrice(twelveMonthPrice);
        }

        const monthlyValues = {
            subscription_12_monthly: twelveMonthPrice
        };

        const w3 = twelveMonthPrice;
        if (!Number.isFinite(w3)) {
            return;
        }

        Object.entries(monthlyFormulaDivisors).forEach(([fieldKey, divisor]) => {
            const input = newArticleMonthlyInputs[fieldKey];
            if (!input) {
                const computed = roundToIntegerMinusCent(w3 / divisor);
                monthlyValues[fieldKey] = computed;
                return;
            }
            const computed = roundToIntegerMinusCent(w3 / divisor);
            if (computed !== null) {
                input.value = formatPrice(computed);
            }
            monthlyValues[fieldKey] = computed;
        });

        const upfrontMultipliers = {
            subscription_12_upfront: { months: 12, source: 'subscription_12_monthly' },
            subscription_9_upfront: { months: 9, source: 'subscription_9_monthly' },
            subscription_6_upfront: { months: 6, source: 'subscription_6_monthly' },
            subscription_3_upfront: { months: 3, source: 'subscription_3_monthly' }
        };

        Object.entries(upfrontMultipliers).forEach(([fieldKey, { months, source }]) => {
            const input = newArticleUpfrontInputs[fieldKey];
            const monthlyValue = monthlyValues[source];
            if (!input || !Number.isFinite(monthlyValue)) {
                return;
            }
            const baseUpfront = monthlyValue * months * 0.9;
            const computed = roundToIntegerMinusCent(baseUpfront);
            if (computed !== null) {
                input.value = formatPrice(computed);
            }
        });

        const singleMonthInput = newArticleUpfrontInputs.subscription_1_upfront;
        const oneMonthValue = monthlyValues.subscription_1_monthly;
        if (singleMonthInput && Number.isFinite(oneMonthValue)) {
            const computed = roundToIntegerMinusCent(oneMonthValue);
            if (computed !== null) {
                singleMonthInput.value = formatPrice(computed);
            }
        }
    };

    basePriceInput.addEventListener('input', updateDerivedFields);
    updateDerivedFields();
}
