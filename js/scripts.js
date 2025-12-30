const etMonths = ['ሐምሌ', 'ነሐሴ', 'መስከረም', 'ጥቅምት', 'ህዳር', 'ታኅሳስ', 'ጥር', 'የካቲቷ', 'መጋቢቷ', 'ሚያዝያ', 'ግንቦቷ', 'ሰኔ'];
const quarterMap = {
    'ሐምሌ': 1, 'ነሐሴ': 1, 'መስከረም': 1,
    'ጥቅምት': 2, 'ህዳር': 2, 'ታኅሳስ': 2,
    'ጥር': 3, 'የካቲቷ': 3, 'መጋቢቷ': 3,
    'ሚያዝያ': 4, 'ግንቦቷ': 4, 'ሰኔ': 4
};

function getEtMonthAndQuarter(date) {
    const month = date.getMonth() + 1;
    const day = date.getDate();
    let etMonth = 'Unknown';
    if (month === 7 && day >= 8 || month === 8 && day <= 6) etMonth = 'ሐምሌ';
    else if (month === 8 && day >= 7 || month === 9 && day <= 5) etMonth = 'ነሐሴ';
    else if (month === 9 && day >= 6 && day <= 10) etMonth = 'መስከረም';
    else if (month === 9 && day >= 11 || month === 10 && day <= 10) etMonth = 'መስከረም';
    else if (month === 10 && day >= 11 || month === 11 && day <= 9) etMonth = 'ጥቅምት';
    else if (month === 11 && day >= 10 || month === 12 && day <= 9) etMonth = 'ህዳር';
    else if (month === 12 && day >= 10 || month === 1 && day <= 8) etMonth = 'ታኅሳስ';
    else if (month === 1 && day >= 9 || month === 2 && day <= 7) etMonth = 'ጥር';
    else if (month === 2 && day >= 8 || month === 3 && day <= 9) etMonth = 'የካቲቷ';
    else if (month === 3 && day >= 10 || month === 4 && day <= 8) etMonth = 'መጋቢቷ';
    else if (month === 4 && day >= 9 || month === 5 && day <= 8) etMonth = 'ሚያዝያ';
    else if (month === 5 && day >= 9 || month === 6 && day <= 7) etMonth = 'ግንቦቷ';
    else if (month === 6 && day >= 8 || month === 7 && day <= 7) etMonth = 'ሰኔ';
    return { etMonth, quarter: quarterMap[etMonth] || 0 };
}

function updateQuarter() {
    const month = document.getElementById('month')?.value;
    if (month) {
        const quarter = quarterMap[month];
        document.getElementById('quarter_label').innerText = `Quarter: ${quarter || 'Unknown'}`;
    }
}

function loadAvailable() {
    const ownerId = document.querySelector('select[name="owner_id"]')?.value;
    const codeId = document.querySelector('select[name="code_id"]')?.value;
    const etMonth = document.getElementById('month')?.value;
    if (ownerId && codeId && etMonth) {
        fetch(`/get_available.php?owner_id=${ownerId}&code_id=${codeId}&et_month=${encodeURIComponent(etMonth)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                    document.getElementById('remaining_month').innerText = '0.00';
                    document.getElementById('remaining_quarter').innerText = '0.00';
                    document.getElementById('remaining_year').innerText = '0.00';
                } else {
                    document.getElementById('remaining_month').innerText = data.monthly || '0.00';
                    document.getElementById('remaining_quarter').innerText = data.quarterly || '0.00';
                    document.getElementById('remaining_year').innerText = data.yearly || '0.00';
                }
            })
            .catch(error => {
                console.error('Error fetching available budgets:', error);
                document.getElementById('remaining_month').innerText = '0.00';
                document.getElementById('remaining_quarter').innerText = '0.00';
                document.getElementById('remaining_year').innerText = '0.00';
            });
    }
}

function calculateFuel() {
    const journey = parseFloat(document.getElementById('journey')?.value) || 0;
    const price = parseFloat(document.getElementById('price')?.value) || 0;
    const current = parseFloat(document.getElementById('current')?.value) || 0;
    const refuelable = journey / 5;
    const total = refuelable * price;
    const newGauge = current + journey;
    const gap = newGauge - current;
    document.getElementById('refuelable').value = refuelable.toFixed(2);
    document.getElementById('total').value = total.toFixed(2);
    document.getElementById('new_gauge').value = newGauge.toFixed(2);
    document.getElementById('gap').value = gap.toFixed(2);
}

window.onload = () => {
    const date = new Date();
    if (document.getElementById('adding_date')) {
        document.getElementById('adding_date').value = date.toISOString().slice(0, 10);
        const etInfo = getEtMonthAndQuarter(date);
        if (document.getElementById('month')) {
            document.getElementById('month').value = etInfo.etMonth;
            updateQuarter();
            loadAvailable();
        }
    }
};