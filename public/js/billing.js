document.addEventListener('DOMContentLoaded', function() {
    // Initialize elements
    const addItemBtn = document.getElementById('addItemBtn');
    const itemsContainer = document.getElementById('itemsContainer');
    const billingItems = document.getElementById('billingItems');
    const subtotalEl = document.getElementById('subtotal');
    const taxAmountEl = document.getElementById('taxAmount');
    const discountEl = document.getElementById('discount');
    const totalEl = document.getElementById('total');
    const billForm = document.getElementById('billForm');
    const patientSearch = document.getElementById('patientSearch');
    const patientIdInput = document.getElementById('patientId');
    const hospitalNoInput = document.getElementById('hospitalNo');
    const patientNameInput = document.getElementById('patientName');
    const consultantSearch = document.getElementById('consultantSearch');
    const consultantIdInput = document.getElementById('consultantId');
    const consultantNameInput = document.getElementById('consultantName');
    const taxRate = parseFloat(document.getElementById('taxRate').value) || 0.18; // Default to 18%
    
    // Initialize autocomplete for patient search
    if (patientSearch) {
        new Autocomplete(patientSearch, {
            search: input => {
                if (input.length < 2) return [];
                return fetch(`/api/patients/search?q=${encodeURIComponent(input)}`)
                    .then(response => response.json())
                    .then(data => data);
            },
            onResultItem: (result, input) => {
                patientIdInput.value = result.id;
                hospitalNoInput.value = result.hospital_no || '';
                patientNameInput.value = result.name || '';
            }
        });
    }

    // Initialize autocomplete for consultant search
    if (consultantSearch) {
        new Autocomplete(consultantSearch, {
            search: input => {
                if (input.length < 2) return [];
                return fetch(`/api/consultants/search?q=${encodeURIComponent(input)}`)
                    .then(response => response.json())
                    .then(data => data);
            },
            onResultItem: (result, input) => {
                consultantIdInput.value = result.id;
                consultantNameInput.value = result.name || '';
            }
        });
    }

    // Add new item row
    function addItemRow(item = null) {
        const rowId = 'item-' + Date.now();
        const itemId = item ? item.id : '';
        const itemName = item ? item.item_name : '';
        const price = item ? item.price : '';
        const qty = item ? item.qty || 1 : 1;
        
        const row = document.createElement('div');
        row.className = 'row mb-3 item-row';
        row.id = rowId;
        row.innerHTML = `
            <div class="col-md-5">
                <select name="items[${rowId}][billing_item_id]" class="form-select item-select" data-row="${rowId}">
                    <option value="">Select an item</option>
                    ${billingItems ? billingItems.innerHTML : ''}
                </select>
                <input type="hidden" name="items[${rowId}][item_name]" class="item-name" value="${itemName}">
            </div>
            <div class="col-md-2">
                <input type="number" name="items[${rowId}][qty]" class="form-control qty" min="1" value="${qty}" data-row="${rowId}">
            </div>
            <div class="col-md-2">
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" name="items[${rowId}][price]" class="form-control price" step="0.01" min="0" value="${price}" data-row="${rowId}">
                </div>
            </div>
            <div class="col-md-2">
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="text" class="form-control item-total" value="${(price * qty).toFixed(2)}" readonly>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm remove-item" data-row="${rowId}">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        
        itemsContainer.appendChild(row);
        
        // Set selected value if item is provided
        if (itemId) {
            const select = row.querySelector('.item-select');
            if (select) select.value = itemId;
        }
        
        // Add event listeners
        const removeBtn = row.querySelector('.remove-item');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => removeItemRow(rowId));
        }
        
        const qtyInput = row.querySelector('.qty');
        if (qtyInput) {
            qtyInput.addEventListener('input', () => calculateRowTotal(rowId));
        }
        
        const priceInput = row.querySelector('.price');
        if (priceInput) {
            priceInput.addEventListener('input', () => calculateRowTotal(rowId));
        }
        
        const itemSelect = row.querySelector('.item-select');
        if (itemSelect) {
            itemSelect.addEventListener('change', (e) => handleItemSelect(e, rowId));
        }
        
        calculateTotals();
        return row;
    }
    
    // Remove item row
    function removeItemRow(rowId) {
        const row = document.getElementById(rowId);
        if (row) {
            row.remove();
            calculateTotals();
        }
        
        // Ensure at least one row exists
        if (document.querySelectorAll('.item-row').length === 0) {
            addItemRow();
        }
    }
    
    // Handle item selection
    function handleItemSelect(event, rowId) {
        const select = event.target;
        const selectedOption = select.options[select.selectedIndex];
        const row = document.getElementById(rowId);
        
        if (row && selectedOption.dataset.price) {
            const priceInput = row.querySelector('.price');
            const itemNameInput = row.querySelector('.item-name');
            
            if (priceInput) {
                priceInput.value = selectedOption.dataset.price;
                priceInput.dispatchEvent(new Event('input'));
            }
            
            if (itemNameInput) {
                itemNameInput.value = selectedOption.textContent;
            }
        }
    }
    
    // Calculate row total
    function calculateRowTotal(rowId) {
        const row = document.getElementById(rowId);
        if (!row) return;
        
        const qtyInput = row.querySelector('.qty');
        const priceInput = row.querySelector('.price');
        const totalInput = row.querySelector('.item-total');
        
        if (qtyInput && priceInput && totalInput) {
            const qty = parseFloat(qtyInput.value) || 0;
            const price = parseFloat(priceInput.value) || 0;
            const total = (qty * price).toFixed(2);
            totalInput.value = total;
            
            calculateTotals();
        }
    }
    
    // Calculate all totals
    function calculateTotals() {
        let subtotal = 0;
        
        document.querySelectorAll('.item-row').forEach(row => {
            const totalInput = row.querySelector('.item-total');
            if (totalInput) {
                subtotal += parseFloat(totalInput.value) || 0;
            }
        });
        
        const discount = parseFloat(discountEl.value) || 0;
        const taxableAmount = Math.max(0, subtotal - discount);
        const tax = taxableAmount * taxRate;
        const total = subtotal + tax - discount;
        
        subtotalEl.textContent = subtotal.toFixed(2);
        taxAmountEl.textContent = tax.toFixed(2);
        totalEl.textContent = total.toFixed(2);
        
        // Update hidden fields
        document.getElementById('subtotalValue').value = subtotal.toFixed(2);
        document.getElementById('taxAmountValue').value = tax.toFixed(2);
        document.getElementById('totalValue').value = total.toFixed(2);
    }
    
    // Event listeners
    if (addItemBtn) {
        addItemBtn.addEventListener('click', () => addItemRow());
    }
    
    if (discountEl) {
        discountEl.addEventListener('input', calculateTotals);
    }
    
    // Form submission
    if (billForm) {
        billForm.addEventListener('submit', function(e) {
            // Validate form
            const patientId = patientIdInput ? patientIdInput.value.trim() : '';
            if (!patientId) {
                e.preventDefault();
                if (typeof showErrorModal === 'function') {
                    showErrorModal('Please select a patient.', 'Validation Error');
                } else {
                    alert('Please select a patient');
                }
                return false;
            }
            
            // Validate at least one item
            const itemRows = document.querySelectorAll('.item-row');
            let hasValidItems = false;
            
            itemRows.forEach(row => {
                const qty = parseFloat(row.querySelector('.qty').value) || 0;
                const price = parseFloat(row.querySelector('.price').value) || 0;
                if (qty > 0 && price > 0) {
                    hasValidItems = true;
                }
            });
            
            if (!hasValidItems) {
                e.preventDefault();
                if (typeof showErrorModal === 'function') {
                    showErrorModal('Please add at least one valid item to the bill.', 'Validation Error');
                } else {
                    alert('Please add at least one valid item to the bill');
                }
                return false;
            }

            if (billForm.dataset.confirmed === '1') {
                billForm.dataset.confirmed = '';
                return true;
            }

            e.preventDefault();
            const msg = 'Are you sure you want to save this bill?';
            if (typeof showConfirmModal === 'function') {
                showConfirmModal(msg, function () {
                    billForm.dataset.confirmed = '1';
                    billForm.submit();
                });
            } else if (confirm(msg)) {
                billForm.submit();
            }
            return false;
        });
    }
    
    // Initialize with one empty row if no rows exist
    if (document.querySelectorAll('.item-row').length === 0) {
        addItemRow();
    }
});
