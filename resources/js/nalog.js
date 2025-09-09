// Funkcija za inicijalizaciju
function initialize() {
    console.log('DOM loaded');
    setupProductInput();
    setupCeOznakaFetchBihnel();
    setupProductList();
    setupProductListBihnel()
    //posaljiNalog();
    posaljiNoviNalog();
    getOrderNumber();
}

// Funkcija za dobijanje rednog broja naloga
async function getOrderNumber() {
    try {
        const response = await fetch('/getOrderNumber');
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        const orderNumberElement = document.getElementById('orderNumber');
        if (orderNumberElement) {
            orderNumberElement.value = data.orderNumber;
        }
        return data.orderNumber;
    } catch (error) {
        console.error("Error fetching order number:", error);
        return null;
    }
}

// Postavljanje događaja za pretragu proizvoda
function setupProductInput() {
    console.log('Setting up product input');
    const productInput = document.getElementById('productInput');
    const datalist = document.getElementById('productSuggestions');

    if (productInput && datalist) {
        productInput.addEventListener('input', async () => {
            const input = productInput.value;
            console.log('Selected value:', productInput.value);
            const selectedValue = productInput.value;
            const options = Array.from(datalist.options).map(option => option.value);
            if (options.includes(selectedValue)) {
                console.log('Selected value from datalist:', selectedValue);
                // Perform actions based on the selected value
                if (selectedValue.includes('BIHNEL')) {
                    console.log('Selected value includes BIHNEL');
                    // how to disable field metraza and color grey
                    document.getElementById('metraza').style.backgroundColor = 'grey';
                    document.getElementById('metraza').disabled = true;
                    document.getElementById('vrstaProvodnika').style.backgroundColor = 'grey';
                    document.getElementById('vrstaProvodnika').disabled = true;
                    document.getElementById('tip').style.backgroundColor = 'grey';
                    document.getElementById('tip').disabled = true;
                    setupCeOznakaFetchBihnel();
                    setupProductListBihnel();
                } else if (selectedValue.includes('DK-')) {
                    console.log('Selected value includes DK proizvodi');
                }
                else {
                    //enable these fields
                    document.getElementById('metraza').style.backgroundColor = '';
                    document.getElementById('metraza').disabled = false;
                    document.getElementById('vrstaProvodnika').style.backgroundColor = '';
                    document.getElementById('vrstaProvodnika').disabled = false;
                    document.getElementById('tip').style.backgroundColor = '';
                    document.getElementById('tip').disabled = false;
                    setupProductList();
                }

            }
            datalist.innerHTML = ''; // Očisti prethodne sugestije

            if (input.length > 0) {
                console.log('Fetching data...lista');
                try {
                    const response = await fetch(`/products?query=${input}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    const data = await response.json();

                    const uniqueProducts = new Set();
                    data.forEach(product => {
                        if (!uniqueProducts.has(product.SkraceniNaziv)) {
                            uniqueProducts.add(product.SkraceniNaziv);
                            const option = document.createElement('option');
                            option.value = product.SkraceniNaziv;
                            console.log('Product:', product.SkraceniNaziv);
                            datalist.appendChild(option);
                        }
                    });
                } catch (error) {
                    console.error("Error fetching data:f", error);
                }
            }
        });
    }
}

// Postavljanje događaja za dobijanje CE oznake
function setupCeOznakaFetch() {
    console.log('Setting up CE oznaka fetch');
    const nazivInput = document.getElementById('productInput');
    const vrstaProvodnikaInput = document.getElementById('vrstaProvodnika');
    const ceOznakaInput = document.getElementById('ceOznaka');
    const klasaInput = document.getElementById('klasaOpasnosti');
    const unBrojInput = document.getElementById('unBroj');

    if (nazivInput && vrstaProvodnikaInput && ceOznakaInput && klasaInput && unBrojInput) {
        const fetchCeOznaka = async () => {
            const naziv = nazivInput.value;
            const vrstaProvodnika = vrstaProvodnikaInput.value;

            if (naziv && vrstaProvodnika) {
                try {
                    const response = await fetch(`/getCeOznaka?naziv=${naziv}&vrstaProvodnika=${vrstaProvodnika}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    const data = await response.json();

                    ceOznakaInput.value = data.CEMarkNumber;
                    klasaInput.value = data.HazardClass;
                    unBrojInput.value = data.UNNumber;
                } catch (error) {
                    console.error("Error fetching dataW:", error);
                }
            }
        };

        nazivInput.addEventListener('input', fetchCeOznaka);
        vrstaProvodnikaInput.addEventListener('change', fetchCeOznaka);
    }
}

function setupCeOznakaFetchBihnel() {
    console.log('Setting up CE oznaka fetch Bihnel');
    const nazivInput = document.getElementById('productInput');
    //const vrstaProvodnikaInput = document.getElementById('vrstaProvodnika');
    const ceOznakaInput = document.getElementById('ceOznaka');
    const klasaInput = document.getElementById('klasaOpasnosti');
    const unBrojInput = document.getElementById('unBroj');

    if (nazivInput && ceOznakaInput && klasaInput && unBrojInput) {
        const fetchCeOznaka = async () => {
            const naziv = nazivInput.value;
            //const vrstaProvodnika = vrstaProvodnikaInput.value;

            if (naziv) {
                try {
                    const response = await fetch(`/getCeOznakaBihnel?naziv=${naziv}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    console.log("Ovo je ce novi resp:" + response);
                    const data = await response.json();

                    ceOznakaInput.value = data.CEMarkNumber;
                    klasaInput.value = data.HazardClass;
                    unBrojInput.value = data.UNNumber;
                } catch (error) {
                    console.error("Error fetching dataP:", error);
                }
            }
        };

        nazivInput.addEventListener('input', fetchCeOznaka);

    }
}

// Postavljanje događaja za prikaz liste proizvoda
function setupProductList() {
    console.log('Setting up product list');
    const productInput = document.getElementById('productInput');
    const metrazaInput = document.getElementById('metraza');
    const vrstaProvodnikaInput = document.getElementById('vrstaProvodnika');
    const tipInput = document.getElementById('tip');
    const productListNew = document.getElementById('productListNew');

    if (productInput && metrazaInput && vrstaProvodnikaInput && productListNew) {
        const fetchProducts = async () => {
            const input = productInput.value;
            const metraza = metrazaInput.value;
            const provodnik = vrstaProvodnikaInput.value;
            const tip = tipInput.value;
            productListNew.innerHTML = ''; // Očisti prethodnu listu

            if (input.length > 0 && metraza.length > 0) {
                try {
                    const response = await fetch(`/productslist?query=${input}&uom_meter=${metraza}&provodnik=${provodnik}&tip=${tip}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    const data = await response.json();
                    console.log(data);

                    // Sort data by productNumera in ascending order
                    data.sort((a, b) => a.NumeraProizvoda - b.NumeraProizvoda);

                    data.forEach(product => {
                        const listItem = document.createElement('li');
                        listItem.style.listStyleType = 'none';
                        listItem.classList.add('mb-2', 'flex', 'items-center');

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.name = 'selectedProducts';
                        checkbox.value = product.id;
                        checkbox.classList.add('form-check-input', 'mr-2');

                        const productNumera = product.NumeraProizvoda < 10 ? '0' + product.NumeraProizvoda : product.NumeraProizvoda;
                        const productText = document.createTextNode(` Numera: ${productNumera}`);

                        const inputBox = document.createElement('input');
                        inputBox.type = 'text';
                        inputBox.name = product.id;
                        inputBox.className = "productListNewItem";
                        inputBox.classList.add('form-control', 'rounded-md', 'shadow-sm', 'ml-2', 'dark:bg-gray-700', 'dark:text-gray-200');
                        inputBox.style.maxWidth = '100px';
                        inputBox.style.height = '30px';

                        // Store the value entered in the input box
                        inputBox.addEventListener('input', (event) => {
                            const value = event.target.value;
                            console.log(`id:${product.id}, quantity:${value}`);
                        });

                        listItem.appendChild(checkbox);
                        listItem.appendChild(productText);
                        listItem.appendChild(inputBox);
                        productListNew.appendChild(listItem);
                    });
                } catch (error) {
                    console.error("Error fetching data:R", error);
                }
            }
        };

        //productInput.addEventListener('input', fetchProducts);
        metrazaInput.addEventListener('input', fetchProducts);
        vrstaProvodnikaInput.addEventListener('change', fetchProducts);
        tipInput.addEventListener('change', fetchProducts);
    }
}
function setupProductListBihnel() {
    console.log('Setting up productlist Bihnel');
    const productInput = document.getElementById('productInput');
    const productListNew = document.getElementById('productListNew');

    if (productInput && productInput.value.trim() !== "") {
        const fetchProducts = async () => {
            const input = productInput.value.trim();
            productListNew.innerHTML = ''; // Očisti prethodnu listu

            if (input.length > 0) {
                try {
                    const response = await fetch(`/productslistBihnel?query=${input}`);
                    if (!response.ok) throw new Error('Network response was not ok');
                    const data = await response.json();

                    // Sort data by productNumera in ascending order
                    data.sort((a, b) => a.NumeraProizvoda - b.NumeraProizvoda);

                    data.forEach(product => {
                        const listItem = document.createElement('li');
                        listItem.style.listStyleType = 'none';
                        listItem.classList.add('mb-2', 'flex', 'items-center');

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.name = 'selectedProducts';
                        checkbox.value = product.id;
                        checkbox.classList.add('form-check-input', 'mr-2');

                        const productNumera = product.UoM_meter;
                        const productText = document.createTextNode(` Metraža: ${productNumera}`);

                        const inputBox = document.createElement('input');
                        inputBox.type = 'text';
                        inputBox.name = 'productValue';
                        inputBox.classList.add('form-control', 'rounded-md', 'shadow-sm', 'ml-2', 'dark:bg-gray-700', 'dark:text-gray-200');
                        inputBox.style.maxWidth = '100px';
                        inputBox.style.height = '30px';

                        listItem.appendChild(checkbox);
                        listItem.appendChild(productText);
                        listItem.appendChild(inputBox);
                        productListNew.appendChild(listItem);
                    });
                } catch (error) {
                    console.error("Error fetching data:c", error);
                }
            }
        };
        fetchProducts();
    } else {
        console.log("Nema product inputa ili je prazan");
    }
}


function posaljiNoviNalog() {
    document.getElementById("pregledBtn").addEventListener("click", function () {
        const orderNumber = document.getElementById("orderNumber").value;
        const user_id = document.getElementById("user_id").value;
        const orderDate = document.getElementById("orderDate").value;
        const description = document.getElementById("productInput").value;
        const metraza = document.getElementById("metraza").value;
        const status = document.getElementById("status").value;
        const vrstaProvodnika = document.getElementById("vrstaProvodnika").value;
        const tip = document.getElementById("tip").value;
        const bojaDuzinaProvodnika = document.getElementById("bojaDuzinaProvodnika").value;
        const pakovanje = document.getElementById("pakovanje").value;
        const atestPaketa = document.getElementById("atestPaketa").value;
        const ceOznaka = document.getElementById("ceOznaka").value;
        const klasaOpasnosti = document.getElementById("klasaOpasnosti").value;
        const unBroj = document.getElementById("unBroj").value;
        const rokIsporuke = document.getElementById("rokIsporuke").value;
        const datumPredaje = document.getElementById("datumPredaje").value;
        const datumPrijema = document.getElementById("datumPrijema").value;
        const napomena = document.getElementById("napomena").value;

        const podaci = {
            OrderNumber: orderNumber,
            user_id: user_id,
            OrderDate: orderDate,
            Description: description,
            metraza: metraza,
            Status: status,
            VrstaProvodnika: vrstaProvodnika,
            Tip: tip,
            BojaDuzinaProvodnika: bojaDuzinaProvodnika,
            Pakovanje: pakovanje,
            AtestPaketa: atestPaketa,
            CeOznaka: ceOznaka,
            KlasaOpasnosti: klasaOpasnosti,
            UNBroj: unBroj,
            RokIsporuke: rokIsporuke,
            DatumPredaje: datumPredaje,
            DatumPrijema: datumPrijema,
            Napomena: napomena,
            productListNew: [...document.querySelectorAll(".productListNewItem")]
                .filter(item => item.value > 0)
                .map(item => {
                    return {
                        id: item.name, // "name" atribut je postavljen na id proizvoda
                        quantity: item.value // "value" atribut je vrednost koju korisnik unese
                    };
                })
        };

        fetch("/productionorders", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
            },
            body: JSON.stringify(podaci)
        })
            .then(response => {
                if (response.headers.get('content-type').includes('application/json')) {
                    return response.json();
                } else {
                    throw new Error('Response is not JSON');
                }
            })
            .then(data => {
                alert("Podaci su poslani!");
                console.log(data);
                //refresh current page
                window.location.reload();
            })
            .catch(error => {
                alert("Greška pri slanju podataka!");
                console.error("Error:", error);
            });
    });
}




// Pokreni inicijalizaciju kada se DOM učita
document.addEventListener("DOMContentLoaded", initialize);
