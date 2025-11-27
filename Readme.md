# Responsive Video

This Module provides an Media Type Called "Responsive Video"

## Plugins
Basically this module is served with a Plugin for [Cloudinary](https://cloudinary.com).
If you use any other Video Converter feel free to create your own Plugin.
Make sure your Plugin extends `ResponsiveVideoConverterApiPluginPluginBase`.
That way it can be found by the Responsive Video Module.

After that you can choose your Plugin in the Responsive Video Settings (admin/config/media/responsive-video).
At the moment this Readme was written there is no Menu Link (so sorry), so you have to use the path given.

Adjust your Plugins Config form to get all the Information needed for the API.

## Functionality
This Module allows you to add
- Responsive Videos
- Video Styles
- Responsive Video Styles
- Video Formats

_Responsive Videos_  are a type of Media. If you want to use them, just add the Responsive Video Type to your Media-Fields on
your entities.

_Video Styles_ are a config Entity. You can give them a name and set width and hight of a video.

_Responsive Video Styles_ are a config Entity. You can set Breakpoints on Responsive video Styles
and choose the _Video Styles_ that are allowed for this responsive Video Style.

_Video Formats_ are what the name says. You can set the Name, the File-Ending and the weight.
The lower the weight of the Format, the higher is the Priority. A Format with weight 1 will be listed before
a format with weight 10.
Formats can be _mp4_, _webm_, _mov_ etc.
If your Format is mp4 the module will **automatically** create a version WITH av1 and a version without av1.

## Output
The output of a responsive video will be a `<video>` tag with all possible sources given from all your
_video Styles_ crossed with all your formats. See [Hook Preprocess](./src/Hook/HookPreprocess.php) for more insights.
Information will be rendered on [media--responsive-video.html.twig](./templates/media--responsive-video.html.twig).




