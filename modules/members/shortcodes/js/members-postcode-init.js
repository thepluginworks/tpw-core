(function(){
  if (!window.TPWCorePostcode || !TPWCorePostcode.bind) return;
  if (!window.tpwCorePostcode || !window.tpwCorePostcode.enabled) return;
  function bindMembersForm(scope){
    try {
      TPWCorePostcode.bind({
        form: scope || '.tpw-member-form form',
        postcode: '#postcode',
        lookupBtn: '#tpw-postcode-lookup-btn',
        selectWrap: '.tpw-postcode-select-wrap',
        messageBox: '.tpw-postcode-message',
        fields: {
          line1: '#address1',
          line2: '#address2',
          city: '#town',
          county: '#county',
          country: '#country'
        },
        countryDefault: 'GB'
      });
    } catch(e){}
  }
  // Bind on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ bindMembersForm(); });
  } else { bindMembersForm(); }
})();
