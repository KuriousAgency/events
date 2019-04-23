(function($) {

if (typeof Craft.Events === 'undefined') {
    Craft.Events = {};
}

Craft.Events.TicketEdit = Garnish.Base.extend({
    rowHtml: 0,
    totalNewRows: 0,

    $container: null,
    $ticketContainer: null,
    $ticketRows: null,
    $addBtn: null,
    $capacity: null,
    $quantity: null,

    currentStartTime: null,
    currentEndTime: null,
    $startDate: null,
    $startTime: null,
    $endDate: null,
    $endTime: null,
    $allDay: null,

    init: function(id, rowHtml) {
        this.rowHtml = rowHtml;
        this.$container = $('#' + id);

        this.$ticketContainer = this.$container.find('.create-tickets-container');
        this.$ticketRows = this.$ticketContainer.find('.create-tickets');
        this.$addBtn = this.$container.find('.add-ticket');
        this.$capacity = this.$container.find('#capacity');
        this.$quantity = this.$container.find('.ticket-quantity');

        for (var i = 0; i < this.$ticketRows.length; i++) {
            new Craft.Events.TicketEditRow(this, this.$ticketRows[i], i);
        }
        
        this.addListener(this.$addBtn, 'click', 'addTicket');
        this.addListener(this.$quantity, 'change', 'sumAllQuantities');
    },

    addTicket: function() {
        this.totalNewRows++;

        var id = 'new' + this.totalNewRows;

        var bodyHtml = this.getParsedBlockHtml(this.rowHtml.bodyHtml, id),
            footHtml = this.getParsedBlockHtml(this.rowHtml.footHtml, id);

        var $newRow = $(bodyHtml).appendTo(this.$ticketContainer);

        Garnish.$bod.append(footHtml);
        
        Craft.initUiElements($newRow);

        new Craft.Events.TicketEditRow(this, $newRow, id);
    },

    getParsedBlockHtml: function(html, id) {
        if (typeof html == 'string') {
            return html.replace(/__ROWID__/g, id);
        } else {
            return '';
        }
    },

    sumAllQuantities: function() {
        var quantity = 0;

        $.each($('body').find('.ticket-quantity'), function() {
            quantity += Number($(this).val());
        });

        this.$capacity.val(quantity);
    }
});

Craft.Events.TicketEditRow = Garnish.Base.extend({

    id: null,
    editContainer: null,

    $container: null,
    $settingsContainer: null,

    $settingsBtn: null,
    $deleteBtn: null,
    $capacity: null,
    $quantity: null,

    init: function(editContainer, row, id) {
        this.id = id;
        this.editContainer = editContainer;

        this.$container = $(row);
        this.$settingsContainer = this.$container.find('.create-tickets-settings');

        this.$settingsBtn = this.$container.find('.settings.icon');
        this.$deleteBtn = this.$container.find('.delete.icon.button');
        this.$capacity = $('body').find('#capacity');
        this.$quantity = this.$container.find('.ticket-quantity');

        this.addListener(this.$settingsBtn, 'click', 'settingsRow');
        this.addListener(this.$deleteBtn, 'click', 'deleteRow');
        this.addListener(this.$quantity, 'change', 'sumAllQuantities');
    },

    settingsRow: function() {
        if (this.$settingsContainer.is(':visible')) {
            this.$settingsBtn.removeClass('active');
            this.$settingsContainer.velocity('slideUp');
        } else {
            this.$settingsBtn.addClass('active');
            this.$settingsContainer.velocity('slideDown');
        }
    },

    deleteRow: function() {
        this.$container.remove();

        this.sumAllQuantities();
    },

    sumAllQuantities: function() {
        var quantity = 0;

        $.each($('body').find('.ticket-quantity'), function() {
            quantity += Number($(this).val());
        });

        this.$capacity.val(quantity);
    }
});


})(jQuery);
