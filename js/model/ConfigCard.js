Ext.define('phpdoe.model.ConfigCard', {
    extend     : 'Ext.data.Model',
    idProperty : 'id',
    fields     : [
        { name:'id', type:'string' },
        { name:'card', type:'string' },
        { name:'label', type:'string' }
    ]
});