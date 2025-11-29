/**
 * GuidePost Admin JavaScript
 */

(function($) {
    'use strict';

    const GuidePostAdmin = {
        calendar: null,

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initDashboard();
            this.initCalendar();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Appointment status change
            $(document).on('change', '.guidepost-status-select', this.updateAppointmentStatus.bind(this));

            // Delete confirmation
            $(document).on('click', '.guidepost-delete-btn', this.confirmDelete.bind(this));

            // Close popup when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.guidepost-event-popup, .fc-event').length) {
                    $('.guidepost-event-popup').remove();
                }
            });

            // Appointment mode toggle (In Person / Virtual)
            $(document).on('change', 'input[name="appointment_mode"]', function() {
                const mode = $(this).val();
                if (mode === 'in_person') {
                    $('#in-person-fields').show();
                    $('#virtual-fields').hide();
                } else {
                    $('#in-person-fields').hide();
                    $('#virtual-fields').show();
                }
            });

            // Service change - update end time display
            $(document).on('change', '#service_id', function() {
                const duration = $(this).find(':selected').data('duration');
                const bookingTime = $('#booking_time').val();
                if (duration && bookingTime) {
                    const endTime = self.calculateEndTime(bookingTime, duration);
                    $('#end-time-display').text(endTime);
                }
            });

            // Time change - update end time display
            $(document).on('change', '#booking_time', function() {
                const duration = $('#service_id').find(':selected').data('duration');
                const bookingTime = $(this).val();
                if (duration && bookingTime) {
                    const endTime = self.calculateEndTime(bookingTime, duration);
                    $('#end-time-display').text(endTime);
                }
            });
        },

        /**
         * Initialize dashboard
         */
        initDashboard: function() {
            // Load dashboard statistics if on dashboard page
            if ($('.guidepost-dashboard-widgets').length) {
                this.loadDashboardStats();
            }
        },

        /**
         * Load dashboard statistics
         */
        loadDashboardStats: function() {
            // Stats are loaded server-side now
        },

        /**
         * Initialize FullCalendar
         */
        initCalendar: function() {
            const calendarEl = document.getElementById('guidepost-calendar');

            if (!calendarEl || typeof FullCalendar === 'undefined') {
                return;
            }

            const self = this;

            this.calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: window.guidepostCalendarEvents || [],
                eventTimeFormat: {
                    hour: 'numeric',
                    minute: '2-digit',
                    meridiem: 'short'
                },
                slotMinTime: '06:00:00',
                slotMaxTime: '22:00:00',
                allDaySlot: false,
                nowIndicator: true,
                eventClick: function(info) {
                    self.showEventPopup(info.event, info.el);
                },
                eventDidMount: function(info) {
                    // Add status class to event (convert underscores to dashes for CSS)
                    const status = info.event.extendedProps.status.replace(/_/g, '-');
                    info.el.classList.add('guidepost-event-' + status);

                    // Add tooltip
                    info.el.setAttribute('title',
                        info.event.extendedProps.customer + '\n' +
                        info.event.extendedProps.service + '\n' +
                        info.event.extendedProps.provider
                    );
                },
                datesSet: function() {
                    // Could reload events when date range changes
                },
                height: 'auto',
                aspectRatio: 1.8
            });

            this.calendar.render();
        },

        /**
         * Show event popup
         */
        showEventPopup: function(event, el) {
            // Remove any existing popup
            $('.guidepost-event-popup').remove();

            const props = event.extendedProps;
            const startTime = event.start ? event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
            const endTime = event.end ? event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
            const dateStr = event.start ? event.start.toLocaleDateString([], {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'}) : '';

            const statusLabels = {
                'pending': 'Pending',
                'approved': 'Approved',
                'completed': 'Completed',
                'canceled': 'Canceled',
                'no_show': 'No Show'
            };

            const editUrl = guidepost_admin.admin_url + 'admin.php?page=guidepost-appointments&action=edit&id=' + event.id;

            const popup = $('<div class="guidepost-event-popup">' +
                '<div class="guidepost-popup-header" style="background-color: ' + (event.backgroundColor || '#c16107') + '">' +
                    '<strong>' + props.service + '</strong>' +
                    '<div class="guidepost-popup-actions">' +
                        '<a href="' + editUrl + '" class="guidepost-popup-edit-btn" title="Edit Appointment">Edit</a>' +
                        '<span class="guidepost-popup-close">&times;</span>' +
                    '</div>' +
                '</div>' +
                '<div class="guidepost-popup-body">' +
                    '<p><strong>Customer:</strong> ' + props.customer + '</p>' +
                    (props.email ? '<p><strong>Email:</strong> <a href="mailto:' + props.email + '">' + props.email + '</a></p>' : '') +
                    (props.phone ? '<p><strong>Phone:</strong> ' + props.phone + '</p>' : '') +
                    '<p><strong>Provider:</strong> ' + props.provider + '</p>' +
                    '<p><strong>Date:</strong> ' + dateStr + '</p>' +
                    '<p><strong>Time:</strong> ' + startTime + ' - ' + endTime + ' (' + props.duration + ' min)</p>' +
                    '<p><strong>Price:</strong> $' + parseFloat(props.price).toFixed(2) + '</p>' +
                    '<p><strong>Status:</strong> <span class="guidepost-status guidepost-status-' + props.status.replace(/_/g, '-') + '">' + (statusLabels[props.status] || props.status) + '</span></p>' +
                '</div>' +
            '</div>');

            // Position popup near the clicked element
            const rect = el.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            popup.css({
                position: 'absolute',
                top: rect.bottom + scrollTop + 5,
                left: Math.min(rect.left, window.innerWidth - 320),
                zIndex: 10000
            });

            $('body').append(popup);

            // Close button
            popup.find('.guidepost-popup-close').on('click', function() {
                popup.remove();
            });
        },

        /**
         * Update appointment status
         */
        updateAppointmentStatus: function(e) {
            const $select = $(e.target);
            const appointmentId = $select.data('appointment-id');
            const newStatus = $select.val();

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_update_appointment_status',
                    nonce: guidepost_admin.nonce,
                    appointment_id: appointmentId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        // Update row styling
                        $select.closest('tr').attr('data-status', newStatus);
                    } else {
                        alert(response.data.message || 'Failed to update status');
                        // Revert select
                        $select.val($select.data('original-status'));
                    }
                },
                error: function() {
                    alert('Failed to update status');
                    $select.val($select.data('original-status'));
                }
            });
        },

        /**
         * Confirm delete
         */
        confirmDelete: function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Calculate end time from start time and duration
         */
        calculateEndTime: function(startTime, durationMinutes) {
            const parts = startTime.split(':');
            const date = new Date();
            date.setHours(parseInt(parts[0], 10));
            date.setMinutes(parseInt(parts[1], 10) + parseInt(durationMinutes, 10));

            let hours = date.getHours();
            let minutes = date.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;

            return hours + ':' + minutes + ' ' + ampm;
        }
    };

    /**
     * Communications Module
     */
    const GuidePostCommunications = {
        /**
         * Initialize
         */
        init: function() {
            if (!$('.guidepost-communications-content').length) {
                return;
            }

            this.bindEvents();
            this.initRecipientToggle();
            this.initSmtpToggle();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Recipient type toggle
            $('#recipient_type').on('change', this.toggleRecipientFields.bind(this));

            // Customer selection
            $('#customer_id').on('change', this.loadCustomerDetails.bind(this));

            // Preview email button
            $('#preview-email-btn').on('click', this.previewEmail.bind(this));

            // Test SMTP button
            $('#test-smtp-btn').on('click', this.testSmtp.bind(this));

            // SMTP enabled toggle
            $('input[name="smtp_enabled"]').on('change', this.toggleSmtpFields.bind(this));

            // SMTP auth toggle
            $('input[name="smtp_auth"]').on('change', this.toggleSmtpAuthFields.bind(this));

            // SMTP presets
            $('.guidepost-preset-btn').on('click', this.applySmtpPreset.bind(this));

            // Modal close
            $(document).on('click', '.guidepost-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.guidepost-modal', function(e) {
                if ($(e.target).hasClass('guidepost-modal')) {
                    $(this).hide();
                }
            });

            // Variable copy (old style)
            $(document).on('click', '.guidepost-var-copy', this.copyVariable.bind(this));

            // Personalization tag copy (new style)
            $(document).on('click', '.guidepost-tag-item', this.copyTagToClipboard.bind(this));

            // View email in log
            $(document).on('click', '.guidepost-view-email', this.viewEmail.bind(this));
        },

        /**
         * Initialize recipient toggle state
         */
        initRecipientToggle: function() {
            this.toggleRecipientFields();
        },

        /**
         * Initialize SMTP toggle state
         */
        initSmtpToggle: function() {
            this.toggleSmtpFields();
            this.toggleSmtpAuthFields();
        },

        /**
         * Toggle recipient fields based on type
         */
        toggleRecipientFields: function() {
            const type = $('#recipient_type').val();

            if (type === 'customer') {
                $('#customer-select-row').show();
                $('#manual-email-row, #manual-name-row').hide();
            } else {
                $('#customer-select-row').hide();
                $('#manual-email-row, #manual-name-row').show();
                $('#customer-info-panel').hide();
            }
        },

        /**
         * Load customer details
         */
        loadCustomerDetails: function() {
            const customerId = $('#customer_id').val();

            if (!customerId) {
                $('#customer-info-panel').hide();
                return;
            }

            const self = this;

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_get_customer_details',
                    nonce: guidepost_admin.nonce,
                    customer_id: customerId
                },
                success: function(response) {
                    if (response.success) {
                        self.displayCustomerInfo(response.data);
                    }
                }
            });
        },

        /**
         * Display customer info
         */
        displayCustomerInfo: function(data) {
            const customer = data.customer;
            const appointments = data.appointments || [];
            const communications = data.communications || [];

            const initials = (customer.first_name.charAt(0) + customer.last_name.charAt(0)).toUpperCase();

            let html = '<div class="customer-header">' +
                '<div class="customer-avatar">' + initials + '</div>' +
                '<div class="customer-info">' +
                    '<p class="customer-name">' + customer.first_name + ' ' + customer.last_name + '</p>' +
                    '<p class="customer-email">' + customer.email + '</p>' +
                '</div>' +
            '</div>';

            html += '<div class="customer-stats">' +
                '<div class="stat-item">' +
                    '<div class="stat-value">' + appointments.length + '</div>' +
                    '<div class="stat-label">Appointments</div>' +
                '</div>' +
                '<div class="stat-item">' +
                    '<div class="stat-value">' + communications.length + '</div>' +
                    '<div class="stat-label">Emails Sent</div>' +
                '</div>' +
            '</div>';

            if (appointments.length > 0) {
                html += '<div class="recent-section">' +
                    '<h4>Recent Appointments</h4>' +
                    '<div class="recent-list">';

                appointments.slice(0, 3).forEach(function(apt) {
                    html += '<div class="recent-item">' +
                        '<strong>' + apt.service_name + '</strong><br>' +
                        '<span style="color: #666;">' + apt.booking_date + '</span>' +
                    '</div>';
                });

                html += '</div></div>';
            }

            $('#customer-info-content').html(html);
            $('#customer-info-panel').show();
        },

        /**
         * Preview email
         */
        previewEmail: function() {
            const templateId = $('#template_id').val();
            const customMessage = $('#custom_message').val();
            const customerId = $('#customer_id').val();

            if (!templateId) {
                alert('Please select a template first.');
                return;
            }

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_preview_email',
                    nonce: guidepost_admin.nonce,
                    template_id: templateId,
                    custom_message: customMessage,
                    customer_id: customerId
                },
                success: function(response) {
                    if (response.success) {
                        $('#preview-subject').text(response.data.subject);

                        // Write to iframe
                        const iframe = document.getElementById('preview-frame');
                        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                        iframeDoc.open();
                        iframeDoc.write(response.data.html);
                        iframeDoc.close();

                        $('#email-preview-modal').show();
                    } else {
                        alert(response.data.message || 'Failed to generate preview');
                    }
                },
                error: function() {
                    alert('Failed to generate preview');
                }
            });
        },

        /**
         * Test SMTP connection
         */
        testSmtp: function() {
            const $btn = $('#test-smtp-btn');
            const originalText = $btn.html();

            $btn.html('<span class="dashicons dashicons-update spin"></span> Sending...').prop('disabled', true);

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_send_test_email',
                    nonce: guidepost_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert('Error: ' + (response.data.message || 'Failed to send test email'));
                    }
                },
                error: function() {
                    alert('Failed to send test email. Please check your settings.');
                },
                complete: function() {
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Toggle SMTP settings fields
         */
        toggleSmtpFields: function() {
            const enabled = $('input[name="smtp_enabled"]').is(':checked');

            if (enabled) {
                $('.smtp-setting').removeClass('hidden');
            } else {
                $('.smtp-setting').addClass('hidden');
            }

            this.toggleSmtpAuthFields();
        },

        /**
         * Toggle SMTP auth fields
         */
        toggleSmtpAuthFields: function() {
            const authEnabled = $('input[name="smtp_auth"]').is(':checked');
            const smtpEnabled = $('input[name="smtp_enabled"]').is(':checked');

            if (authEnabled && smtpEnabled) {
                $('.smtp-auth-setting').removeClass('hidden');
            } else {
                $('.smtp-auth-setting').addClass('hidden');
            }
        },

        /**
         * Apply SMTP preset
         */
        applySmtpPreset: function(e) {
            const $btn = $(e.currentTarget);

            $('#smtp_host').val($btn.data('host'));
            $('#smtp_port').val($btn.data('port'));
            $('#smtp_encryption').val($btn.data('encryption'));

            // Enable SMTP and auth
            $('input[name="smtp_enabled"]').prop('checked', true).trigger('change');
            $('input[name="smtp_auth"]').prop('checked', true).trigger('change');
        },

        /**
         * Copy variable to clipboard
         */
        copyVariable: function(e) {
            const text = $(e.currentTarget).text();

            navigator.clipboard.writeText(text).then(function() {
                const $el = $(e.currentTarget);
                const originalBg = $el.css('background-color');
                $el.css('background-color', '#95c93d');
                setTimeout(function() {
                    $el.css('background-color', originalBg);
                }, 300);
            });
        },

        /**
         * Copy personalization tag to clipboard
         */
        copyTagToClipboard: function(e) {
            const $item = $(e.currentTarget);
            const tag = $item.data('tag') || $item.find('code').text();

            if (!tag) return;

            navigator.clipboard.writeText(tag).then(function() {
                // Visual feedback - add copied class
                $item.addClass('copied');

                // Show "Copied!" tooltip
                const $code = $item.find('code');
                const originalText = $code.text();
                $code.text('Copied!');

                setTimeout(function() {
                    $item.removeClass('copied');
                    $code.text(originalText);
                }, 1500);
            }).catch(function(err) {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = tag;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);

                $item.addClass('copied');
                setTimeout(function() {
                    $item.removeClass('copied');
                }, 1500);
            });
        },

        /**
         * View email details
         */
        viewEmail: function(e) {
            const emailId = $(e.currentTarget).data('email-id');

            // For now, show a message - full implementation would load email details via AJAX
            alert('Email ID: ' + emailId + '\n\nFull email viewer will be integrated with the Customer Manager page.');
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.guidepost-modal').hide();
        }
    };

    /**
     * Customer Manager Module
     */
    const GuidePostCustomers = {
        /**
         * Initialize
         */
        init: function() {
            if (!$('.guidepost-customer-detail-page, .guidepost-customers-table').length) {
                return;
            }

            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Notes - button click handler
            $(document).on('click', '#add-note-btn', this.addNote.bind(this));
            $(document).on('click', '.guidepost-note-pin', this.pinNote.bind(this));
            $(document).on('click', '.guidepost-note-delete', this.deleteNote.bind(this));

            // Flags
            $(document).on('click', '.dismiss-flag-btn', this.dismissFlag.bind(this));
            $(document).on('click', '#add-flag-btn', this.showAddFlagModal.bind(this));
            $(document).on('submit', '#guidepost-add-flag-form', this.addFlag.bind(this));

            // Credits
            $(document).on('click', '#adjust-credits-btn', this.showCreditsModal.bind(this));
            $(document).on('submit', '#guidepost-credits-form', this.adjustCredits.bind(this));

            // Status
            $(document).on('click', '#change-status-btn', this.showStatusModal.bind(this));
            $(document).on('submit', '#guidepost-status-form', this.updateStatus.bind(this));

            // Modals
            $(document).on('click', '.guidepost-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.guidepost-modal', function(e) {
                if ($(e.target).hasClass('guidepost-modal')) {
                    $(this).hide();
                }
            });

            // ICS Export
            $(document).on('click', '#export-appointments-ics', this.exportICS.bind(this));

            // Quick Search
            $(document).on('keyup', '#customer-search', this.debounce(this.searchCustomers.bind(this), 300));
        },

        /**
         * Add note
         */
        addNote: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const customerId = $btn.data('customer-id');
            const $textarea = $('#new-note-text');
            const $noteType = $('#new-note-type');
            const noteText = $textarea.val();
            const noteType = $noteType.val();
            const originalText = $btn.html();

            if (!noteText.trim()) {
                alert('Please enter a note.');
                $textarea.focus();
                return;
            }

            const data = {
                action: 'guidepost_add_customer_note',
                nonce: guidepost_admin.nonce,
                customer_id: customerId,
                note_text: noteText,
                note_type: noteType
            };

            $btn.html('<span class="dashicons dashicons-update spin"></span> Saving...').prop('disabled', true);

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Reload page to show new note
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Failed to add note');
                    }
                },
                error: function() {
                    alert('Failed to add note. Please try again.');
                },
                complete: function() {
                    $btn.html(originalText).prop('disabled', false);
                }
            });
        },

        /**
         * Pin/unpin note
         */
        pinNote: function(e) {
            const $btn = $(e.currentTarget);
            const noteId = $btn.data('note-id');
            const isPinned = $btn.data('pinned') ? 0 : 1;

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_toggle_note_pin',
                    nonce: guidepost_admin.nonce,
                    note_id: noteId,
                    is_pinned: isPinned
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Failed to update note');
                    }
                }
            });
        },

        /**
         * Delete note
         */
        deleteNote: function(e) {
            if (!confirm('Are you sure you want to delete this note?')) {
                return;
            }

            const $btn = $(e.currentTarget);
            const noteId = $btn.data('note-id');

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_delete_customer_note',
                    nonce: guidepost_admin.nonce,
                    note_id: noteId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.guidepost-note-item').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'Failed to delete note');
                    }
                }
            });
        },

        /**
         * Dismiss flag
         */
        dismissFlag: function(e) {
            const $btn = $(e.currentTarget);
            const flagId = $btn.data('flag-id');

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_dismiss_flag',
                    nonce: guidepost_admin.nonce,
                    flag_id: flagId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.guidepost-flag-item').fadeOut(300, function() {
                            $(this).remove();

                            // Update badge count
                            const $badge = $('.guidepost-menu-badge');
                            if ($badge.length) {
                                const count = parseInt($badge.text()) - 1;
                                if (count <= 0) {
                                    $badge.remove();
                                } else {
                                    $badge.text(count);
                                }
                            }

                            // Show "no flags" message if empty
                            if ($('.guidepost-flag-item').length === 0) {
                                $('.guidepost-flags-list').html('<p class="guidepost-no-flags">No active flags</p>');
                            }
                        });
                    } else {
                        alert(response.data.message || 'Failed to dismiss flag');
                    }
                }
            });
        },

        /**
         * Show add flag modal
         */
        showAddFlagModal: function() {
            $('#add-flag-modal').show();
        },

        /**
         * Add flag
         */
        addFlag: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $btn = $form.find('button[type="submit"]');

            const data = {
                action: 'guidepost_add_customer_flag',
                nonce: guidepost_admin.nonce,
                customer_id: $form.find('[name="customer_id"]').val(),
                flag_type: $form.find('[name="flag_type"]').val(),
                message: $form.find('[name="message"]').val(),
                trigger_date: $form.find('[name="trigger_date"]').val()
            };

            if (!data.message.trim()) {
                alert('Please enter a flag message.');
                return;
            }

            $btn.prop('disabled', true);

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Failed to add flag');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Failed to add flag. Please try again.');
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Show credits modal
         */
        showCreditsModal: function() {
            $('#credits-modal').show();
        },

        /**
         * Show status modal
         */
        showStatusModal: function() {
            $('#status-modal').show();
        },

        /**
         * Adjust credits
         */
        adjustCredits: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $btn = $form.find('button[type="submit"]');

            const type = $form.find('[name="credit_type"]:checked').val();
            const amount = parseInt($form.find('[name="amount"]').val());
            const reason = $form.find('[name="reason"]').val();

            if (!amount || amount <= 0) {
                alert('Please enter a valid amount.');
                return;
            }

            const data = {
                action: 'guidepost_adjust_credits',
                nonce: guidepost_admin.nonce,
                customer_id: $form.find('[name="customer_id"]').val(),
                type: type,
                amount: amount,
                reason: reason
            };

            $btn.prop('disabled', true);

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Failed to adjust credits');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Failed to adjust credits. Please try again.');
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Update status
         */
        updateStatus: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $btn = $form.find('button[type="submit"]');
            const customerId = $form.find('[name="customer_id"]').val();
            const newStatus = $form.find('[name="status"]').val();

            $btn.prop('disabled', true);

            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_update_customer_status',
                    nonce: guidepost_admin.nonce,
                    customer_id: customerId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Failed to update status');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Failed to update status');
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Export ICS
         */
        exportICS: function(e) {
            e.preventDefault();

            const appointmentId = $(e.currentTarget).data('appointment-id');
            const exportUrl = guidepost_admin.ajax_url + '?action=guidepost_export_ics&nonce=' +
                            guidepost_admin.nonce + '&appointment_id=' + appointmentId;

            window.location.href = exportUrl;
        },

        /**
         * Search customers
         */
        searchCustomers: function(e) {
            const query = $(e.target).val();
            const currentUrl = new URL(window.location.href);

            if (query) {
                currentUrl.searchParams.set('s', query);
            } else {
                currentUrl.searchParams.delete('s');
            }

            // Update URL without reloading (for bookmarking)
            window.history.replaceState({}, '', currentUrl.toString());
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.guidepost-modal').hide();
        },

        /**
         * Debounce helper
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    };

    /**
     * Backup & Restore Module
     */
    const GuidePostBackup = {
        /**
         * Initialize
         */
        init: function() {
            if (!$('.guidepost-backup-page').length) {
                return;
            }

            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Restore button click (opens modal)
            $(document).on('click', '.guidepost-restore-btn', this.openRestoreModal.bind(this));

            // Delete backup confirmation
            $(document).on('click', '.guidepost-delete-backup-btn', this.confirmDelete.bind(this));

            // Modal close
            $(document).on('click', '.guidepost-modal-close, .guidepost-modal-cancel', this.closeModal.bind(this));
            $(document).on('click', '#guidepost-restore-modal', function(e) {
                if ($(e.target).is('#guidepost-restore-modal')) {
                    $(this).hide();
                }
            });

            // Select all / deselect all for restore tables
            $(document).on('click', '#select-all-tables', this.selectAllTables.bind(this));
            $(document).on('click', '#deselect-all-tables', this.deselectAllTables.bind(this));
        },

        /**
         * Open restore modal and load backup info
         */
        openRestoreModal: function(e) {
            e.preventDefault();

            const filename = $(e.currentTarget).data('backup');
            const self = this;

            // Set filename in hidden field
            $('#restore-backup-filename').val(filename);

            // Show loading in modal
            $('#restore-backup-info').html('<p>Loading backup information...</p>');
            $('#restore-tables-list').empty();

            // Show modal
            $('#guidepost-restore-modal').show();

            // Load backup info via AJAX
            $.ajax({
                url: guidepost_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'guidepost_get_backup_info',
                    nonce: guidepost_admin.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        self.displayBackupInfo(response.data);
                    } else {
                        $('#restore-backup-info').html('<p style="color: #dc3545;">Error: ' + (response.data.message || 'Failed to load backup info') + '</p>');
                    }
                },
                error: function() {
                    $('#restore-backup-info').html('<p style="color: #dc3545;">Failed to load backup information.</p>');
                }
            });
        },

        /**
         * Display backup info in modal
         */
        displayBackupInfo: function(data) {
            const tableLabels = {
                'services': 'Services',
                'providers': 'Providers',
                'customers': 'Customers',
                'appointments': 'Appointments',
                'payments': 'Payments',
                'customer_notes': 'Customer Notes',
                'customer_flags': 'Customer Flags',
                'customer_documents': 'Customer Documents',
                'customer_purchases': 'Customer Purchases',
                'credit_history': 'Credit History',
                'provider_services': 'Provider-Service Mappings',
                'working_hours': 'Working Hours',
                'days_off': 'Days Off',
                'email_templates': 'Email Templates',
                'notifications': 'Notification History'
            };

            // Display backup info
            let infoHtml = '<p><strong>Created:</strong> ' + data.created_at + '</p>';
            infoHtml += '<p><strong>Plugin Version:</strong> ' + data.plugin_version + '</p>';
            infoHtml += '<p><strong>Site:</strong> ' + data.site_url + '</p>';

            if (data.record_counts) {
                let totalRecords = 0;
                for (const key in data.record_counts) {
                    totalRecords += data.record_counts[key];
                }
                infoHtml += '<p><strong>Total Records:</strong> ' + totalRecords + '</p>';
            }

            $('#restore-backup-info').html(infoHtml);

            // Display table selection checkboxes
            const tables = data.tables_included || [];
            let tablesHtml = '';

            // Add select all / deselect all buttons
            tablesHtml += '<div style="grid-column: 1 / -1; margin-bottom: 10px;">';
            tablesHtml += '<button type="button" class="button button-small" id="select-all-tables">Select All</button> ';
            tablesHtml += '<button type="button" class="button button-small" id="deselect-all-tables">Deselect All</button>';
            tablesHtml += '</div>';

            tables.forEach(function(table) {
                const label = tableLabels[table] || table;
                const count = data.record_counts && data.record_counts[table] ? ' (' + data.record_counts[table] + ')' : '';
                tablesHtml += '<label>';
                tablesHtml += '<input type="checkbox" name="restore_tables[]" value="' + table + '" checked>';
                tablesHtml += ' ' + label + count;
                tablesHtml += '</label>';
            });

            // Add settings option if included
            if (data.includes_settings) {
                tablesHtml += '<label>';
                tablesHtml += '<input type="checkbox" name="restore_tables[]" value="settings" checked>';
                tablesHtml += ' Plugin Settings';
                tablesHtml += '</label>';
            }

            $('#restore-tables-list').html(tablesHtml);
        },

        /**
         * Select all tables
         */
        selectAllTables: function(e) {
            e.preventDefault();
            $('#restore-tables-list input[type="checkbox"]').prop('checked', true);
        },

        /**
         * Deselect all tables
         */
        deselectAllTables: function(e) {
            e.preventDefault();
            $('#restore-tables-list input[type="checkbox"]').prop('checked', false);
        },

        /**
         * Confirm delete
         */
        confirmDelete: function(e) {
            if (!confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#guidepost-restore-modal').hide();
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        GuidePostAdmin.init();
        GuidePostCommunications.init();
        GuidePostCustomers.init();
        GuidePostBackup.init();
    });

})(jQuery);
