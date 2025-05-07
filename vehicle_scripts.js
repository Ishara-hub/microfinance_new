// vehicle_scripts.js
$(document).ready(function () {
    // Handle vehicle selection
    $('#vehicle_id').change(function () {
        let vehicleId = $(this).val();
        if (vehicleId) {
            $.ajax({
                url: 'get_vehicle_details.php',
                type: 'POST',
                data: { id: vehicleId },
                dataType: 'json',
                beforeSend: function () {
                    $('#vehicleDetailsContainer').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading vehicle details...</div>').show();
                },
                success: function (response) {
                    if (response.status === 'success') {
                        let vehicle = response.data;
                        let details = `
                            <div class="vehicle-details">
                                <h5><i class="fas fa-car me-2"></i>${vehicle.vehicle_no}</h5>
                                <div class="row mt-3">
                                    <div class="col-md-4 mb-2"><strong>Make:</strong> ${vehicle.make_display || vehicle.make}</div>
                                    <div class="col-md-4 mb-2"><strong>Type:</strong> ${vehicle.type_display || vehicle.type}</div>
                                    <div class="col-md-4 mb-2"><strong>Model:</strong> ${vehicle.model}</div>
                                    <div class="col-md-4 mb-2"><strong>Year:</strong> ${vehicle.year_of_make}</div>
                                    <div class="col-md-4 mb-2"><strong>Engine No:</strong> ${vehicle.engine_no}</div>
                                    <div class="col-md-4 mb-2"><strong>Chassis No:</strong> ${vehicle.chassis_no}</div>
                                    ${vehicle.current_mileage ? `<div class="col-md-4 mb-2"><strong>Mileage:</strong> ${vehicle.current_mileage} km</div>` : ''}
                                    <div class="col-md-4 mb-2"><strong>Market Value:</strong> Rs. ${parseFloat(vehicle.market_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                                </div>
                            </div>
                        `;
                        $('#vehicleDetailsContainer').html(details).show();
                    } else {
                        $('#vehicleDetailsContainer').hide();
                        showAlert('danger', response.message || 'Error loading vehicle details');
                    }
                },
                error: function (xhr, status, error) {
                    $('#vehicleDetailsContainer').html('<div class="alert alert-danger">Error loading vehicle details</div>');
                    console.error("Error loading vehicle:", status, error);
                }
            });
        } else {
            $('#vehicleDetailsContainer').hide();
        }
    });

    // Save new vehicle
    $('#saveVehicleBtn').click(function () {
        // Validate form
        let isValid = true;
        $('#vehicleForm [required]').each(function () {
            if (!$(this).val()) {
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });

        if (!isValid) {
            showAlert('warning', 'Please fill all required fields marked with *');
            return;
        }

        let form = $('#vehicleForm')[0];
        let formData = new FormData(form);
        let btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'save_vehicle.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,   // important for FormData
            contentType: false,   // important for FormData
            success: function (response) {
                if (response.status === 'success') {
                    // Add the new vehicle to the dropdown and select it
                    $('#vehicle_id').append(`<option value="${response.id}" selected>${response.vehicle_no}</option>`);

                    // Show the vehicle details
                    let details = `
                        <div class="vehicle-details">
                            <h5><i class="fas fa-car me-2"></i>${response.vehicle_no}</h5>
                            <div class="row mt-3">
                                <div class="col-md-4 mb-2"><strong>Make:</strong> ${response.make}</div>
                                <div class="col-md-4 mb-2"><strong>Type:</strong> ${response.type}</div>
                                <div class="col-md-4 mb-2"><strong>Model:</strong> ${response.model}</div>
                                <div class="col-md-4 mb-2"><strong>Year:</strong> ${response.year_of_make}</div>
                                <div class="col-md-4 mb-2"><strong>Engine No:</strong> ${response.engine_no}</div>
                                <div class="col-md-4 mb-2"><strong>Chassis No:</strong> ${response.chassis_no}</div>
                                ${response.current_mileage ? `<div class="col-md-4 mb-2"><strong>Mileage:</strong> ${response.current_mileage} km</div>` : ''}
                                <div class="col-md-4 mb-2"><strong>Market Value:</strong> Rs. ${parseFloat(response.market_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                            </div>
                        </div>
                    `;
                    $('#vehicleDetailsContainer').html(details).show();

                    // Reset and close the modal safely
                    let vehicleForm = document.getElementById('vehicleForm');
                    if (vehicleForm) {
                        vehicleForm.reset();
                    }

                    let vehicleModal = $('#vehicleModal');
                    if (vehicleModal.length) {
                        vehicleModal.modal('hide');
                    }

                    showAlert('success', 'Vehicle saved successfully!');
                } else {
                    showAlert('danger', response.message || 'Error saving vehicle');
                }
            },
            error: function (xhr, status, error) {
                let errorMessage = xhr.responseJSON && xhr.responseJSON.message
                    ? xhr.responseJSON.message
                    : 'Error saving vehicle. Please try again.';
                showAlert('danger', errorMessage);
                console.error("Error saving vehicle:", status, error);
            },
            complete: function () {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Vehicle');
            }
        });
    });

    // Reset form when modal is closed
    $('#vehicleModal').on('hidden.bs.modal', function () {
        let vehicleForm = document.getElementById('vehicleForm');
        if (vehicleForm) {
            vehicleForm.reset();
        }
        $('#vehicleForm [required]').removeClass('is-invalid');
    });

    // Helper function to show alerts
    function showAlert(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 1100;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        // Remove any existing alerts first
        $('.alert-dismissible').alert('close');

        // Add new alert
        $('body').append(alertHtml);

        // Auto-close after 5 seconds
        setTimeout(function () {
            $('.alert-dismissible').alert('close');
        }, 5000);
    }
});
