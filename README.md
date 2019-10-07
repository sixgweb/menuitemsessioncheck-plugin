# Menu Item Session Check

Hide menu item(s) by checking RainLab Session component on referenced CMS page or layout.

## Requirements

[RainLab.User](http://octobercms.com/plugin/rainlab-user) and [RainLab.Pages](http://octobercms.com/plugin/rainlab-pages)

## Instructions

**Layout Method (recommended)**

1. Add the Session component to a CMS layout and set desired permissions
2. Create a CMS page or static page that uses the layout created in step 1
3. Create a menu item linking to the page created in step 2
4. Repeat step 1 if additional permission types are required

*Tip: Moving your layout HTML to a partial will ease maintenance and improve reusability*

**CMS Page Method**

1. Add the Session component to a CMS page and set permissions
2. Create a menu item linking to the CMS page

This method does not work for Static pages