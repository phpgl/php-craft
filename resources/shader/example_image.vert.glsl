#version 330 core

layout (location = 0) in vec2 a_pos;
layout (location = 1) in vec2 a_uv;
layout (location = 2) in vec3 a_model_frame;

uniform mat4 u_view;
uniform mat4 u_projection;
uniform vec2 u_resolution;

out vec2 v_uv;
out float frame;

void main()
{
    // our quad is defined in a way that it would fill
    // the whole screen so we have to scale to the proper size
    // our visu elephpant is a 64x64 sprite with 3 frames meaning 
    // we have to scale it to 32x32 to represent a single frame
    vec2 scaledpos = (a_pos / u_resolution) * 32;

    // because i want the elephpant to be a bit bigger..
    scaledpos *= 2;

    // forward the uv and frame to the fragment shader
    v_uv = a_uv;
    frame = a_model_frame.z;

    // calculate the final screenspace position
    gl_Position = u_projection * u_view * vec4(a_model_frame.xy, 0.0, 1.0) + vec4(scaledpos, 0.0, 0.0);
}